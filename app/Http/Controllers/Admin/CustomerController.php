<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Resources\CustomerResource;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;

/** GET /v1/admin/customers — read-only list of registered customers with order totals. */
class CustomerController extends Controller
{
    public function index(Request $request)
    {
        $filters = $request->validate([
            'search' => ['sometimes', 'nullable', 'string', 'max:100'],
            'per_page' => ['sometimes', 'integer', 'min:1', 'max:100'],
            'page' => ['sometimes', 'integer', 'min:1'],
        ]);

        $search = $filters['search'] ?? null;
        $notCancelled = fn (Builder $q) => $q->where('status', '!=', 'cancelled');

        $customers = User::query()
            ->where('role', 'customer')
            ->when($search, function (Builder $q) use ($search) {
                $like = '%'.addcslashes($search, '%_\\').'%';
                $q->where(fn (Builder $w) => $w
                    ->where('name', 'like', $like)
                    ->orWhere('email', 'like', $like)
                    ->orWhere('phone', 'like', $like));
            })
            ->withCount(['orders', 'orders as active_orders_count' => $notCancelled])
            ->withSum(['orders as total_spent' => $notCancelled], 'total')
            ->withMax('orders as last_order_at', 'placed_at')
            ->latest()
            ->paginate(min((int) ($filters['per_page'] ?? 20), 100))
            ->withQueryString();

        return CustomerResource::collection($customers);
    }
}
