<?php

namespace App\Services;

use App\Models\Coupon;
use App\Models\Order;
use App\Models\User;
use App\Support\Shipping;

/**
 * Single source of truth for coupon eligibility and order totals. The cart
 * (to display) and checkout (to charge) both call totals(), so they can't drift.
 *
 * Rules: one coupon per order; the discount applies to products only and never
 * exceeds the subtotal; shipping is charged on the subtotal AFTER the discount
 * (a free-shipping coupon waives it). Uses are counted from non-cancelled
 * orders, so cancelling an order gives the use back automatically.
 */
class CouponService
{
    public function findByCode(?string $code): ?Coupon
    {
        $code = Coupon::normalize($code);

        return $code === '' ? null : Coupon::query()->where('code', $code)->first();
    }

    /**
     * Why this coupon can't be used right now, or null if it can.
     * Customer-specific rules (per-customer limit, first order) need $user —
     * guests are re-checked when they sign in to check out.
     *
     * $locking: checkout passes true. Inside its transaction a plain read could
     * use a snapshot taken before another checkout committed; locking reads
     * always see the latest rows, so a usage limit can't be overshot.
     */
    public function ineligibilityReason(Coupon $coupon, float $subtotal, ?User $user, bool $locking = false): ?string
    {
        $uses = fn () => $locking ? $coupon->redemptions()->lockForUpdate() : $coupon->redemptions();

        if (! $coupon->is_active) {
            return 'This coupon code isn\'t valid.';
        }
        if ($coupon->starts_at && $coupon->starts_at->isFuture()) {
            return 'This coupon isn\'t active yet.';
        }
        if ($coupon->expires_at && $coupon->expires_at->isPast()) {
            return 'This coupon has expired.';
        }
        if ($coupon->usage_limit !== null && $uses()->count() >= $coupon->usage_limit) {
            return 'This coupon has reached its usage limit.';
        }

        if ($user) {
            if ($coupon->per_user_limit !== null
                && $uses()->where('user_id', $user->id)->count() >= $coupon->per_user_limit) {
                return $coupon->per_user_limit === 1
                    ? 'You\'ve already used this coupon.'
                    : 'You\'ve used this coupon the maximum number of times.';
            }
            $previousOrders = Order::query()->where('user_id', $user->id)->where('status', '!=', 'cancelled');
            if ($coupon->first_order_only && ($locking ? $previousOrders->lockForUpdate() : $previousOrders)->exists()) {
                return 'This coupon is for your first order only.';
            }
        }

        $minimum = (float) $coupon->min_order_amount;
        if ($subtotal < $minimum) {
            return 'Add '.self::money($minimum - $subtotal).' more to use this coupon (minimum order '.self::money($minimum).').';
        }

        return null;
    }

    public function discountFor(Coupon $coupon, float $subtotal): float
    {
        $discount = match ($coupon->type) {
            Coupon::TYPE_PERCENT => $subtotal * ((float) $coupon->value) / 100,
            Coupon::TYPE_FIXED => (float) $coupon->value,
            default => 0.0, // free_shipping
        };

        if ($coupon->type === Coupon::TYPE_PERCENT && $coupon->max_discount !== null) {
            $discount = min($discount, (float) $coupon->max_discount);
        }

        return round(min($discount, $subtotal), 2);
    }

    /**
     * @return array{subtotal: float, discount: float, shipping: float, total: float,
     *               free_shipping: bool, amount_to_free_shipping: float,
     *               coupon: ?Coupon, coupon_error: ?string}
     */
    public function totals(float $subtotal, ?Coupon $coupon, ?User $user, bool $locking = false): array
    {
        $subtotal = round($subtotal, 2);
        $error = $coupon && $subtotal > 0 ? $this->ineligibilityReason($coupon, $subtotal, $user, $locking) : null;
        $applies = $coupon && $subtotal > 0 && $error === null;

        $discount = $applies ? $this->discountFor($coupon, $subtotal) : 0.0;
        $freeShipping = $applies && $coupon->type === Coupon::TYPE_FREE_SHIPPING;
        $afterDiscount = round($subtotal - $discount, 2);
        $shipping = $freeShipping ? 0.0 : Shipping::feeFor($afterDiscount);

        return [
            'subtotal' => $subtotal,
            'discount' => $discount,
            'shipping' => $shipping,
            'total' => round($afterDiscount + $shipping, 2),
            'free_shipping' => $freeShipping,
            'amount_to_free_shipping' => $freeShipping ? 0.0 : Shipping::remainingForFree($afterDiscount),
            'coupon' => $coupon,
            'coupon_error' => $error,
        ];
    }

    /** "10% off, up to ₹200" — a short label for offer lists. */
    public function headline(Coupon $coupon): string
    {
        return match ($coupon->type) {
            Coupon::TYPE_PERCENT => rtrim(rtrim(number_format((float) $coupon->value, 2), '0'), '.').'% off'
                .($coupon->max_discount !== null ? ', up to '.self::money((float) $coupon->max_discount) : ''),
            Coupon::TYPE_FIXED => self::money((float) $coupon->value).' off',
            default => 'Free shipping',
        };
    }

    public static function money(float $amount): string
    {
        return '₹'.number_format(round($amount, 2), fmod($amount, 1.0) == 0.0 ? 0 : 2);
    }
}
