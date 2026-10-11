<?php

namespace App\Reports;

use App\Models\Coupon;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder as EloquentBuilder;
use Illuminate\Database\Query\Builder as QueryBuilder;
use Illuminate\Support\Facades\DB;

/**
 * Admin reports, built live from the database for a date range.
 * Each report = columns + a query + a row mapper + summary figures, so the
 * same definition drives the on-screen preview and the CSV download.
 *
 * Revenue figures always exclude cancelled orders.
 */
final class ReportBuilder
{
    public const TYPES = ['orders', 'sales', 'products', 'inventory', 'customers', 'coupons'];

    /** Reports that ignore the date range (a snapshot of now). */
    public const UNDATED = ['inventory'];

    private CarbonImmutable $from;

    private CarbonImmutable $to;

    private ?string $status;

    /** @param  array{from?: string, to?: string, status?: string}  $filters  dates as Y-m-d, inclusive */
    public function __construct(private readonly string $type, array $filters = [])
    {
        $tz = config('app.timezone');
        $this->from = CarbonImmutable::parse($filters['from'] ?? now($tz)->subDays(29)->toDateString(), $tz)->startOfDay();
        $this->to = CarbonImmutable::parse($filters['to'] ?? now($tz)->toDateString(), $tz)->endOfDay();
        $this->status = $filters['status'] ?? null;
    }

    /** @return array{title: string, columns: list<string>, query: EloquentBuilder|QueryBuilder, row: callable, summary: callable} */
    public function definition(): array
    {
        return match ($this->type) {
            'orders' => $this->orders(),
            'sales' => $this->sales(),
            'products' => $this->products(),
            'inventory' => $this->inventory(),
            'customers' => $this->customers(),
            'coupons' => $this->coupons(),
        };
    }

    public function filename(string $extension = 'csv'): string
    {
        $range = in_array($this->type, self::UNDATED, true)
            ? now()->toDateString()
            : $this->from->toDateString().'_to_'.$this->to->toDateString();

        return "{$this->type}-report_{$range}.{$extension}";
    }

    public function range(): array
    {
        return in_array($this->type, self::UNDATED, true)
            ? ['from' => null, 'to' => null]
            : ['from' => $this->from->toDateString(), 'to' => $this->to->toDateString()];
    }

    /* ------------------------------------------------------------------ */

    private function ordersInRange(): EloquentBuilder
    {
        return Order::query()->whereBetween('placed_at', [$this->from, $this->to]);
    }

    private function revenueOrders(): EloquentBuilder
    {
        return $this->ordersInRange()->where('status', '!=', 'cancelled');
    }

    private function orders(): array
    {
        $query = $this->ordersInRange()
            ->when($this->status, fn ($q, $s) => $q->where('status', $s))
            ->withSum('items as items_count', 'quantity')
            ->orderBy('placed_at')
            ->orderBy('id');

        return [
            'title' => 'Orders',
            'columns' => ['Order number', 'Placed at', 'Customer', 'Email', 'Phone', 'City', 'State', 'Pincode', 'Items', 'Subtotal (₹)', 'Discount (₹)', 'Coupon', 'Shipping (₹)', 'Total (₹)', 'Payment method', 'Payment status', 'Status'],
            'query' => $query,
            'row' => fn (Order $o) => [
                $o->order_number, $o->placed_at?->format('Y-m-d H:i'), $o->shipping_name, $o->email, $o->shipping_phone,
                $o->shipping_city, $o->shipping_state, $o->shipping_pincode, (int) $o->items_count,
                $this->money($o->subtotal), $this->money($o->discount_amount), $o->coupon_code, $this->money($o->shipping_amount),
                $this->money($o->total), strtoupper((string) $o->payment_method), ucfirst((string) $o->payment_status), ucfirst((string) $o->status),
            ],
            'summary' => function () {
                $all = $this->ordersInRange()->when($this->status, fn ($q, $s) => $q->where('status', $s));
                $paid = (clone $all)->where('status', '!=', 'cancelled');

                return [
                    ['label' => 'Orders', 'value' => $all->count()],
                    ['label' => 'Revenue (excl. cancelled)', 'value' => $this->money($paid->sum('total')), 'money' => true],
                    ['label' => 'Average order', 'value' => $this->money($paid->avg('total') ?? 0), 'money' => true],
                    ['label' => 'Cancelled', 'value' => (clone $all)->where('status', 'cancelled')->count()],
                ];
            },
        ];
    }

    private function sales(): array
    {
        $items = OrderItem::query()
            ->selectRaw('order_id, SUM(quantity) as units')
            ->groupBy('order_id');

        $query = DB::query()
            ->fromSub($this->ordersInRange()->toBase()->select('orders.*'), 'o')
            ->leftJoinSub($items, 'i', 'i.order_id', '=', 'o.id')
            ->selectRaw('DATE(o.placed_at) as day')
            ->selectRaw("SUM(CASE WHEN o.status != 'cancelled' THEN 1 ELSE 0 END) as orders")
            ->selectRaw("SUM(CASE WHEN o.status != 'cancelled' THEN COALESCE(i.units, 0) ELSE 0 END) as units")
            ->selectRaw("SUM(CASE WHEN o.status != 'cancelled' THEN o.subtotal ELSE 0 END) as gross")
            ->selectRaw("SUM(CASE WHEN o.status != 'cancelled' THEN o.discount_amount ELSE 0 END) as discounts")
            ->selectRaw("SUM(CASE WHEN o.status != 'cancelled' THEN o.shipping_amount ELSE 0 END) as shipping")
            ->selectRaw("SUM(CASE WHEN o.status != 'cancelled' THEN o.total ELSE 0 END) as net")
            ->selectRaw("SUM(CASE WHEN o.status = 'cancelled' THEN 1 ELSE 0 END) as cancelled")
            ->groupBy('day')
            ->orderBy('day');

        return [
            'title' => 'Sales by day',
            'columns' => ['Date', 'Orders', 'Units sold', 'Gross sales (₹)', 'Discounts (₹)', 'Shipping (₹)', 'Net revenue (₹)', 'Cancelled orders'],
            'query' => $query,
            'row' => fn ($r) => [
                $r->day, (int) $r->orders, (int) $r->units, $this->money($r->gross), $this->money($r->discounts),
                $this->money($r->shipping), $this->money($r->net), (int) $r->cancelled,
            ],
            'summary' => function () {
                $paid = $this->revenueOrders();
                $count = (clone $paid)->count();

                return [
                    ['label' => 'Net revenue', 'value' => $this->money((clone $paid)->sum('total')), 'money' => true],
                    ['label' => 'Orders', 'value' => $count],
                    ['label' => 'Discounts given', 'value' => $this->money((clone $paid)->sum('discount_amount')), 'money' => true],
                    ['label' => 'Average order', 'value' => $this->money($count ? (clone $paid)->avg('total') : 0), 'money' => true],
                ];
            },
        ];
    }

    private function products(): array
    {
        $query = OrderItem::query()
            ->join('orders', 'orders.id', '=', 'order_items.order_id')
            ->leftJoin('products', 'products.id', '=', 'order_items.product_id')
            ->leftJoin('categories', 'categories.id', '=', 'products.category_id')
            ->whereBetween('orders.placed_at', [$this->from, $this->to])
            ->where('orders.status', '!=', 'cancelled')
            ->groupBy('order_items.product_name', 'order_items.product_sku', 'categories.name', 'products.stock', 'products.is_active')
            ->selectRaw('order_items.product_name as name, order_items.product_sku as sku, categories.name as category')
            ->selectRaw('products.stock as stock, products.is_active as is_active')
            ->selectRaw('SUM(order_items.quantity) as units, SUM(order_items.subtotal) as revenue, COUNT(DISTINCT orders.id) as orders')
            ->orderByDesc('revenue');

        return [
            'title' => 'Product sales',
            'columns' => ['Product', 'SKU', 'Category', 'Units sold', 'Orders', 'Revenue (₹)', 'Stock now', 'Status'],
            'query' => $query,
            'row' => fn ($r) => [
                $r->name, $r->sku, $r->category ?? '—', (int) $r->units, (int) $r->orders, $this->money($r->revenue),
                $r->stock === null ? 'Removed' : (int) $r->stock, $r->is_active === null ? 'Removed' : ($r->is_active ? 'Active' : 'Hidden'),
            ],
            'summary' => function () use ($query) {
                $totals = DB::query()->fromSub((clone $query)->toBase(), 'p')->selectRaw('COUNT(*) as products, COALESCE(SUM(units),0) as units, COALESCE(SUM(revenue),0) as revenue')->first();

                return [
                    ['label' => 'Products sold', 'value' => (int) $totals->products],
                    ['label' => 'Units sold', 'value' => (int) $totals->units],
                    ['label' => 'Revenue', 'value' => $this->money($totals->revenue), 'money' => true],
                ];
            },
        ];
    }

    private function inventory(): array
    {
        $query = Product::query()->with('category:id,name')->orderBy('stock')->orderBy('name');

        return [
            'title' => 'Inventory',
            'columns' => ['Product', 'SKU', 'Category', 'MRP (₹)', 'Sale price (₹)', 'Stock', 'Stock value (₹)', 'Status', 'Alert'],
            'query' => $query,
            'row' => fn (Product $p) => [
                $p->name, $p->sku, $p->category?->name ?? '—', $this->money($p->price), $p->sale_price !== null ? $this->money($p->sale_price) : '',
                (int) $p->stock, $this->money(($p->sale_price ?? $p->price) * $p->stock), $p->is_active ? 'Active' : 'Hidden',
                match (true) { $p->stock <= 0 => 'Out of stock', $p->stock <= 5 => 'Low stock', default => '' },
            ],
            'summary' => fn () => [
                ['label' => 'Products', 'value' => Product::count()],
                ['label' => 'Units in stock', 'value' => (int) Product::sum('stock')],
                ['label' => 'Stock value', 'value' => $this->money(Product::query()->selectRaw('COALESCE(SUM(COALESCE(sale_price, price) * stock), 0) as v')->value('v')), 'money' => true],
                ['label' => 'Low or out of stock', 'value' => Product::where('stock', '<=', 5)->count()],
            ],
        ];
    }

    private function customers(): array
    {
        $orders = Order::query()
            ->whereBetween('placed_at', [$this->from, $this->to])
            ->where('status', '!=', 'cancelled')
            ->whereNotNull('user_id')
            ->groupBy('user_id')
            ->selectRaw('user_id, COUNT(*) as orders, SUM(total) as spent, MAX(placed_at) as last_order');

        $query = User::query()
            ->where('role', 'customer')
            ->leftJoinSub($orders, 'o', 'o.user_id', '=', 'users.id')
            ->select('users.name', 'users.email', 'users.phone', 'users.created_at')
            ->selectRaw('COALESCE(o.orders, 0) as orders, COALESCE(o.spent, 0) as spent, o.last_order')
            ->orderByDesc('spent')
            ->orderBy('users.name');

        return [
            'title' => 'Customers',
            'columns' => ['Name', 'Email', 'Phone', 'Joined', 'Orders in period', 'Spent in period (₹)', 'Last order'],
            'query' => $query,
            'row' => fn ($u) => [
                $u->name, $u->email, $u->phone, $u->created_at?->format('Y-m-d'), (int) $u->orders, $this->money($u->spent),
                $u->last_order ? CarbonImmutable::parse($u->last_order)->format('Y-m-d') : '',
            ],
            'summary' => function () {
                $customers = User::where('role', 'customer');

                return [
                    ['label' => 'Customers', 'value' => (clone $customers)->count()],
                    ['label' => 'New in period', 'value' => (clone $customers)->whereBetween('created_at', [$this->from, $this->to])->count()],
                    ['label' => 'Ordered in period', 'value' => $this->revenueOrders()->whereNotNull('user_id')->distinct()->count('user_id')],
                ];
            },
        ];
    }

    private function coupons(): array
    {
        $uses = Order::query()
            ->whereBetween('placed_at', [$this->from, $this->to])
            ->where('status', '!=', 'cancelled')
            ->whereNotNull('coupon_id')
            ->groupBy('coupon_id')
            ->selectRaw('coupon_id, COUNT(*) as uses, SUM(discount_amount) as discount, SUM(total) as revenue');

        $query = Coupon::query()
            ->leftJoinSub($uses, 'u', 'u.coupon_id', '=', 'coupons.id')
            ->select('coupons.*')
            ->selectRaw('COALESCE(u.uses, 0) as uses, COALESCE(u.discount, 0) as discount, COALESCE(u.revenue, 0) as revenue')
            ->orderByDesc('uses')
            ->orderBy('code');

        return [
            'title' => 'Coupons',
            'columns' => ['Code', 'Type', 'Value', 'Active', 'Uses in period', 'Discount given (₹)', 'Revenue (₹)', 'Expires'],
            'query' => $query,
            'row' => fn (Coupon $c) => [
                $c->code, str_replace('_', ' ', ucfirst($c->type)),
                match ($c->type) { 'percent' => rtrim(rtrim((string) $c->value, '0'), '.').'%', 'fixed' => $this->money($c->value), default => '' },
                $c->is_active ? 'Yes' : 'No', (int) $c->uses, $this->money($c->discount), $this->money($c->revenue), $c->expires_at?->format('Y-m-d') ?? '',
            ],
            'summary' => function () {
                $paid = $this->revenueOrders()->whereNotNull('coupon_id');

                return [
                    ['label' => 'Orders with a coupon', 'value' => (clone $paid)->count()],
                    ['label' => 'Discount given', 'value' => $this->money((clone $paid)->sum('discount_amount')), 'money' => true],
                    ['label' => 'Active coupons', 'value' => Coupon::where('is_active', true)->count()],
                ];
            },
        ];
    }

    private function money(mixed $value): string
    {
        return number_format((float) $value, 2, '.', '');
    }
}
