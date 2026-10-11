<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Cart extends Model
{
    protected $fillable = ['user_id', 'session_token', 'coupon_code'];

    public function items()
    {
        return $this->hasMany(CartItem::class);
    }

    /**
     * What checkout would charge for products right now: current prices, and only
     * lines that can actually be ordered. Expects `items.product` to be loaded.
     */
    public function payableSubtotal(): float
    {
        return round($this->items->sum(function (CartItem $item) {
            $product = $item->product;
            if (! $product || $product->trashed() || ! $product->is_active) {
                return 0;
            }

            return (float) ($product->sale_price ?? $product->price) * $item->quantity;
        }), 2);
    }
}
