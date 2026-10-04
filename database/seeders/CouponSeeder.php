<?php

namespace Database\Seeders;

use App\Models\Coupon;
use Illuminate\Database\Seeder;

/**
 * Demo coupons for local testing. Not run in production (see DatabaseSeeder) —
 * create real offers in Admin → Coupons. Idempotent: matched by code.
 */
class CouponSeeder extends Seeder
{
    public const COUPONS = [
        ['code' => 'WELCOME10', 'description' => '10% off your first order (up to ₹200).', 'type' => Coupon::TYPE_PERCENT, 'value' => 10,
            'max_discount' => 200, 'min_order_amount' => 0, 'per_user_limit' => 1, 'first_order_only' => true],
        ['code' => 'FLAT200', 'description' => 'Flat ₹200 off on orders of ₹1,499 or more.', 'type' => Coupon::TYPE_FIXED, 'value' => 200,
            'min_order_amount' => 1499, 'per_user_limit' => 3],
        ['code' => 'FESTIVE15', 'description' => '15% off on orders of ₹1,999 or more (up to ₹500).', 'type' => Coupon::TYPE_PERCENT, 'value' => 15,
            'max_discount' => 500, 'min_order_amount' => 1999, 'per_user_limit' => 2],
        ['code' => 'FREESHIP', 'description' => 'Free shipping on any order.', 'type' => Coupon::TYPE_FREE_SHIPPING, 'value' => 0,
            'min_order_amount' => 0, 'per_user_limit' => null],
    ];

    public function run(): void
    {
        foreach (self::COUPONS as $coupon) {
            Coupon::query()->updateOrCreate(
                ['code' => $coupon['code']],
                [...$coupon, 'is_active' => true, 'is_public' => true, 'starts_at' => null, 'expires_at' => null, 'usage_limit' => null],
            );
        }

        $this->command?->info('Demo coupons ready: '.implode(', ', array_column(self::COUPONS, 'code')));
    }
}
