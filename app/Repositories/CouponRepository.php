<?php

namespace App\Repositories;

use App\Models\Coupon;
use App\Repositories\Interfaces\CouponRepositoryInterface;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

class CouponRepository implements CouponRepositoryInterface
{
    public function paginateWithStats(?string $search, int $perPage): LengthAwarePaginator
    {
        return $this->withStats(Coupon::query())
            ->when($search, fn (Builder $q) => $q->where(fn (Builder $w) => $w
                ->where('code', 'like', '%'.addcslashes(strtoupper($search), '%_\\').'%')
                ->orWhere('description', 'like', '%'.addcslashes($search, '%_\\').'%')))
            ->latest()
            ->orderByDesc('id')
            ->paginate($perPage)
            ->withQueryString();
    }

    public function availableOffers(): Collection
    {
        return Coupon::query()
            ->live()
            ->where('is_public', true)
            ->withCount('redemptions')
            ->orderBy('min_order_amount')
            ->orderBy('id')
            ->get()
            ->reject(fn (Coupon $c) => $c->usage_limit !== null && $c->redemptions_count >= $c->usage_limit)
            ->values();
    }

    public function store(array $data): Coupon
    {
        return $this->fresh(Coupon::create($data));
    }

    public function update(Coupon $coupon, array $data): Coupon
    {
        $coupon->update($data);

        return $this->fresh($coupon);
    }

    public function orderCount(Coupon $coupon): int
    {
        return $coupon->orders()->count();
    }

    public function delete(Coupon $coupon): void
    {
        $coupon->delete();
    }

    private function withStats(Builder $query): Builder
    {
        return $query
            ->withCount('redemptions as uses')
            ->withSum('redemptions as discount_given', 'discount_amount')
            ->withSum('redemptions as revenue', 'total');
    }

    private function fresh(Coupon $coupon): Coupon
    {
        return $this->withStats(Coupon::query())->findOrFail($coupon->id);
    }
}
