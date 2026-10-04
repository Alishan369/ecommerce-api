<?php

namespace App\Http\Resources;

use App\Models\CartItem;
use App\Models\Coupon;
use App\Services\CouponService;
use App\Support\Shipping;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class CartResource extends JsonResource
{
    /**
     * Prices are the product's *current* price — the same figure checkout charges —
     * not the snapshot stored when the item was added.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $items = $this->items->map(function (CartItem $item) {
            $product = $item->product;
            $unitPrice = $product ? (float) ($product->sale_price ?? $product->price) : (float) $item->price;

            return [
                'id' => $item->id,
                'product_id' => $item->product_id,
                'name' => $product?->name ?? 'Unavailable product',
                'slug' => $product?->slug,
                'sku' => $product?->sku,
                'image_url' => $product?->image_url,
                'fragrance_family' => $product?->fragrance_family,
                'concentration' => $product?->concentration,
                'size_ml' => $product?->size_ml,
                'mrp' => $product ? (float) $product->price : null,
                'unit_price' => $unitPrice,
                'quantity' => $item->quantity,
                'line_total' => round($unitPrice * $item->quantity, 2),
                'stock' => (int) ($product?->stock ?? 0),
                'is_available' => $product !== null && ! $product->trashed() && $product->is_active,
            ];
        })->values();

        // Unavailable lines can't be checked out, so they don't count towards totals.
        $subtotal = round($items->where('is_available', true)->sum('line_total'), 2);

        // Same calculation checkout uses (CouponService::totals), so the cart never promises a different price.
        $coupons = app(CouponService::class);
        $coupon = $this->coupon_code ? $coupons->findByCode($this->coupon_code) : null;
        $totals = $coupons->totals($subtotal, $coupon, $request->user('sanctum'));

        return [
            'items' => $items,
            'subtotal' => $totals['subtotal'],
            'discount_amount' => $totals['discount'],
            'shipping_amount' => $totals['shipping'],
            'total' => $totals['total'],
            'free_shipping_threshold' => Shipping::threshold(),
            'amount_to_free_shipping' => $totals['amount_to_free_shipping'],
            'item_count' => (int) $items->sum('quantity'),
            'coupon' => $this->couponPayload($coupon, $totals, $coupons),
        ];
    }

    /** Applied coupon, including why it currently gives no discount (e.g. cart dropped below the minimum). */
    private function couponPayload(?Coupon $coupon, array $totals, CouponService $coupons): ?array
    {
        if (! $this->coupon_code) {
            return null;
        }

        $error = $coupon ? $totals['coupon_error'] : 'This coupon code isn\'t valid any more.';

        return [
            'code' => $coupon?->code ?? $this->coupon_code,
            'description' => $coupon?->description,
            'headline' => $coupon ? $coupons->headline($coupon) : null,
            'type' => $coupon?->type,
            'is_valid' => $error === null,
            'message' => $error,
            'savings' => $totals['discount'],
            'free_shipping' => $totals['free_shipping'],
        ];
    }
}
