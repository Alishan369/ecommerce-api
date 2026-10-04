<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Resources\OrderResource;
use App\Models\Order;
use App\Models\Product;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * GET /v1/admin/dashboard — headline numbers for the admin home.
 *
 * "Revenue" only counts money the store has or will collect: orders that are
 * not cancelled AND either paid online or Cash on Delivery. Online orders still
 * waiting for payment are excluded.
 */
class DashboardController extends Controller
{
    private const PERIOD_DAYS = 30;

    private const CHART_DAYS = 14;

    public function __invoke(): JsonResponse
    {
        $periodStart = now()->subDays(self::PERIOD_DAYS - 1)->startOfDay();
        $previousStart = $periodStart->copy()->subDays(self::PERIOD_DAYS);

        $current = $this->totals($periodStart, now());
        $previous = $this->totals($previousStart, $periodStart);

        return response()->json(['data' => [
            'period_days' => self::PERIOD_DAYS,
            'kpis' => [
                'revenue' => $current['revenue'],
                'revenue_previous' => $previous['revenue'],
                'orders' => $current['orders'],
                'orders_previous' => $previous['orders'],
                'average_order_value' => $current['orders'] ? round($current['revenue'] / $current['orders'], 2) : 0,
                'customers' => User::query()->where('role', 'customer')->count(),
                'new_customers' => User::query()->where('role', 'customer')->where('created_at', '>=', $periodStart)->count(),
                'to_fulfil' => $this->toFulfil(),
            ],
            'sales_by_day' => $this->salesByDay(),
            'orders_by_status' => $this->ordersByStatus(),
            'payment_methods' => $this->paymentMethods($periodStart),
            'top_products' => $this->topProducts($periodStart),
            'low_stock' => $this->lowStock(),
            'recent_orders' => OrderResource::collection(
                Order::query()->with(['items', 'user:id,name,email,phone'])->latest('placed_at')->orderByDesc('id')->limit(6)->get()
            ),
        ]]);
    }

    /** Orders that bring in money (see class docblock). */
    private function countable(): Builder
    {
        return Order::query()
            ->where('status', '!=', 'cancelled')
            ->where(fn (Builder $q) => $q->where('payment_status', 'paid')->orWhere('payment_method', 'cod'));
    }

    private function totals(Carbon $from, Carbon $to): array
    {
        $row = $this->countable()
            ->whereBetween('placed_at', [$from, $to])
            ->selectRaw('COUNT(*) as orders, COALESCE(SUM(total), 0) as revenue')
            ->first();

        return ['orders' => (int) $row->orders, 'revenue' => round((float) $row->revenue, 2)];
    }

    /** Paid/COD orders the team still has to pack or ship. */
    private function toFulfil(): int
    {
        return $this->countable()->whereIn('status', ['pending', 'confirmed', 'processing'])->count();
    }

    private function salesByDay(): array
    {
        $start = now()->subDays(self::CHART_DAYS - 1)->startOfDay();

        $rows = $this->countable()
            ->where('placed_at', '>=', $start)
            ->selectRaw('DATE(placed_at) as day, COUNT(*) as orders, SUM(total) as revenue')
            ->groupBy('day')
            ->get()
            ->keyBy(fn ($row) => Carbon::parse($row->day)->toDateString());

        // Every day appears, including days with no sales, so the chart's x-axis is continuous.
        return collect(range(0, self::CHART_DAYS - 1))
            ->map(function (int $offset) use ($start, $rows) {
                $date = $start->copy()->addDays($offset)->toDateString();
                $row = $rows->get($date);

                return [
                    'date' => $date,
                    'orders' => (int) ($row->orders ?? 0),
                    'revenue' => round((float) ($row->revenue ?? 0), 2),
                ];
            })
            ->all();
    }

    private function ordersByStatus(): array
    {
        $counts = Order::query()->selectRaw('status, COUNT(*) as total')->groupBy('status')->pluck('total', 'status');

        return collect(Order::STATUSES)->mapWithKeys(fn (string $s) => [$s => (int) ($counts[$s] ?? 0)])->all();
    }

    private function paymentMethods(Carbon $from): array
    {
        return $this->countable()
            ->where('placed_at', '>=', $from)
            ->selectRaw('payment_method, COUNT(*) as orders, SUM(total) as revenue')
            ->groupBy('payment_method')
            ->get()
            ->map(fn ($row) => [
                'method' => $row->payment_method,
                'orders' => (int) $row->orders,
                'revenue' => round((float) $row->revenue, 2),
            ])
            ->all();
    }

    private function topProducts(Carbon $from): array
    {
        return DB::table('order_items')
            ->join('orders', 'orders.id', '=', 'order_items.order_id')
            ->where('orders.status', '!=', 'cancelled')
            ->where(fn ($q) => $q->where('orders.payment_status', 'paid')->orWhere('orders.payment_method', 'cod'))
            ->where('orders.placed_at', '>=', $from)
            ->selectRaw('order_items.product_id, MAX(order_items.product_name) as name, SUM(order_items.quantity) as quantity, SUM(order_items.subtotal) as revenue')
            ->groupBy('order_items.product_id')
            ->orderByDesc('quantity')
            ->limit(5)
            ->get()
            ->map(fn ($row) => [
                'product_id' => $row->product_id,
                'name' => $row->name,
                'quantity' => (int) $row->quantity,
                'revenue' => round((float) $row->revenue, 2),
            ])
            ->all();
    }

    private function lowStock(): array
    {
        return Product::query()
            ->where('is_active', true)
            ->where('stock', '<=', (int) config('shop.low_stock_threshold', 10))
            ->orderBy('stock')
            ->orderBy('name')
            ->limit(8)
            ->get(['id', 'name', 'sku', 'stock', 'fragrance_family'])
            ->map(fn (Product $p) => $p->only(['id', 'name', 'sku', 'stock', 'fragrance_family']))
            ->all();
    }
}
