<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CartItem extends Model
{
    protected $fillable = ['cart_id', 'product_id', 'quantity', 'price'];

    protected $casts = [
        'price' => 'decimal:2',
    ];

    public function cart()
    {
        return $this->belongsTo(Cart::class);
    }

    public function product()
    {
        // Include soft-deleted products so the cart can show them as unavailable instead of erroring.
        return $this->belongsTo(Product::class)->withTrashed();
    }

    public function getSubtotalAttribute(): float
    {
        return $this->price * $this->quantity;
    }
}
