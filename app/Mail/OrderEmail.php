<?php

namespace App\Mail;

use App\Models\Order;

/** Shared, pre-formatted values for the order email templates. */
final class OrderEmail
{
    public static function money(float $amount): string
    {
        return '₹'.number_format(round($amount, 2), fmod($amount, 1.0) == 0.0 ? 0 : 2);
    }

    /** Markdown tables use pipes as separators — keep them out of product names. */
    public static function cell(?string $text): string
    {
        return str_replace(['|', "\n"], ['/', ' '], (string) $text);
    }

    public static function data(Order $order): array
    {
        $site = config('shop.frontend_url');

        return [
            'order' => $order,
            'firstName' => strtok((string) $order->shipping_name, ' ') ?: 'there',
            'placedOn' => $order->placed_at?->format('d M Y, g:i a'),
            'items' => $order->items->map(fn ($item) => [
                'name' => self::cell($item->product_name),
                'quantity' => $item->quantity,
                'amount' => self::money((float) $item->subtotal),
            ])->all(),
            'subtotal' => self::money((float) $order->subtotal),
            'discount' => (float) $order->discount_amount > 0 ? self::money((float) $order->discount_amount) : null,
            'couponCode' => $order->coupon_code,
            'shipping' => (float) $order->shipping_amount > 0 ? self::money((float) $order->shipping_amount) : 'Free',
            'total' => self::money((float) $order->total),
            'orderUrl' => $site.'/order/'.$order->order_number,
            'contactUrl' => $site.'/contact',
        ];
    }
}
