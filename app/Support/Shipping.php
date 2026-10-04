<?php

namespace App\Support;

/**
 * Single source of truth for the shipping charge — used by the cart (to show
 * it) and by checkout (to charge it), so the two can never disagree.
 */
final class Shipping
{
    public static function threshold(): float
    {
        return (float) config('shop.free_shipping_threshold', 499);
    }

    public static function feeFor(float $subtotal): float
    {
        if ($subtotal <= 0 || $subtotal >= self::threshold()) {
            return 0.0;
        }

        return (float) config('shop.shipping_fee', 49);
    }

    /** How much more the customer needs to add for free shipping (0 once qualified). */
    public static function remainingForFree(float $subtotal): float
    {
        return max(0.0, round(self::threshold() - $subtotal, 2));
    }
}
