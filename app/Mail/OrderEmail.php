<?php

namespace App\Mail;

use App\Models\Order;
use App\Support\Money;

/** Shared, pre-formatted values for the order email templates. */
final class OrderEmail
{
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
                'amount' => Money::format((float) $item->subtotal),
            ])->all(),
            'subtotal' => Money::format((float) $order->subtotal),
            'discount' => (float) $order->discount_amount > 0 ? Money::format((float) $order->discount_amount) : null,
            'couponCode' => $order->coupon_code,
            'shipping' => (float) $order->shipping_amount > 0 ? Money::format((float) $order->shipping_amount) : 'Free',
            'total' => Money::format((float) $order->total),
            'orderUrl' => $site.'/order/'.$order->order_number,
            'contactUrl' => $site.'/contact',
        ];
    }
}
