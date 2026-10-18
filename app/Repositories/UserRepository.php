<?php

namespace App\Repositories;

use App\Models\User;
use App\Repositories\Interfaces\UserRepositoryInterface;
use Carbon\CarbonInterface;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;

class UserRepository implements UserRepositoryInterface
{
    public function create(array $data): User
    {
        return User::create($data);
    }

    public function findByEmail(string $email): ?User
    {
        return User::query()->where('email', $email)->first();
    }

    public function paginateCustomers(?string $search, int $perPage): LengthAwarePaginator
    {
        $notCancelled = fn (Builder $q) => $q->where('status', '!=', 'cancelled');

        return User::query()
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
            ->paginate($perPage)
            ->withQueryString();
    }

    public function countCustomers(?CarbonInterface $since = null): int
    {
        return User::query()
            ->where('role', 'customer')
            ->when($since, fn (Builder $q) => $q->where('created_at', '>=', $since))
            ->count();
    }
}
