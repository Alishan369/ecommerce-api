<?php

namespace App\Http\Controllers;

use App\Models\Coupon;
use App\Services\CouponService;
use Illuminate\Http\JsonResponse;

/** GET /coupons — public "Available offers": live, public coupons that still have uses left. */
class CouponController extends Controller
{
    public function index(CouponService $coupons): JsonResponse
    {
        $offers = Coupon::query()
            ->live()
            ->where('is_public', true)
            ->withCount('redemptions')
            ->orderBy('min_order_amount')
            ->orderBy('id')
            ->get()
            ->reject(fn (Coupon $c) => $c->usage_limit !== null && $c->redemptions_count >= $c->usage_limit)
            ->values()
            ->map(fn (Coupon $c) => [
                'code' => $c->code,
                'headline' => $coupons->headline($c),
                'description' => $c->description,
                'type' => $c->type,
                'value' => (float) $c->value,
                'max_discount' => $c->max_discount !== null ? (float) $c->max_discount : null,
                'min_order_amount' => (float) $c->min_order_amount,
                'first_order_only' => $c->first_order_only,
                'expires_at' => $c->expires_at?->toIso8601String(),
            ]);

        return response()->json(['data' => $offers]);
    }
}
