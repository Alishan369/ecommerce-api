<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class OrderResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'order_number' => $this->order_number,
            'email' => $this->email,
            'customer_type' => $this->user_id ? 'registered' : 'guest',
            'status' => $this->status,
            'payment_method' => $this->payment_method,
            'payment_status' => $this->payment_status,
            // Unpaid online order the customer can still pay (or switch to COD) before this time.
            'can_pay' => $this->awaitingPayment(),
            'payment_due_at' => $this->paymentDueAt()?->toIso8601String(),
            // Money was captured after the order was cancelled — the admin must refund it.
            'refund_required' => $this->status === 'cancelled' && $this->payment_status === 'paid',
            // Mirrors OrderRepository::cancelForCustomer so the UI only offers what the API allows.
            'can_cancel' => in_array($this->status, ['pending', 'confirmed'], true) && $this->payment_status !== 'paid',
            'items' => $this->items->map(function ($item) {
                $product = $item->relationLoaded('product') ? $item->product : null;

                return [
                    'product_id' => $item->product_id,
                    'name' => $item->product_name,
                    'sku' => $item->product_sku,
                    'slug' => $product?->slug,
                    'image_url' => $product?->image_url,
                    'fragrance_family' => $product?->fragrance_family,
                    'concentration' => $product?->concentration,
                    'size_ml' => $product?->size_ml,
                    'unit_price' => $item->unit_price,
                    'quantity' => $item->quantity,
                    'subtotal' => $item->subtotal,
                ];
            }),
            'customer' => $this->whenLoaded('user', fn () => $this->user ? [
                'id' => $this->user->id,
                'name' => $this->user->name,
                'email' => $this->user->email,
                'phone' => $this->user->phone,
            ] : null),
            'shipping_address' => [
                'name' => $this->shipping_name,
                'phone' => $this->shipping_phone,
                'address_line' => $this->shipping_address_line,
                'city' => $this->shipping_city,
                'state' => $this->shipping_state,
                'pincode' => $this->shipping_pincode,
            ],
            'subtotal' => $this->subtotal,
            'shipping_amount' => $this->shipping_amount,
            'discount_amount' => $this->discount_amount,
            'coupon_code' => $this->coupon_code,
            'total' => $this->total,
            'notes' => $this->notes,
            'placed_at' => $this->placed_at,
        ];
    }
}
