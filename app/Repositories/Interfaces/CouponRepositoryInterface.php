<?php

namespace App\Repositories\Interfaces;

use App\Models\Coupon;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;

interface CouponRepositoryInterface
{
    /** Admin list with usage stats (uses, discount given, revenue), newest first. */
    public function paginateWithStats(?string $search, int $perPage): LengthAwarePaginator;

    /** Live, public coupons that still have uses left, cheapest threshold first. */
    public function availableOffers(): Collection;

    public function store(array $data): Coupon;

    public function update(Coupon $coupon, array $data): Coupon;

    public function orderCount(Coupon $coupon): int;

    public function delete(Coupon $coupon): void;
}
