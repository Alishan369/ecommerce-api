<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Cart extends Model
{
    protected $fillable = ['user_id', 'session_token'];

    public function items()
    {
        return $this->hasMany(CartItem::class);
    }

    public function getTotalAttribute(): float
    {
        return $this->items->sum(fn ($item) => $item->price * $item->quantity);
    }

    public function getItemCountAttribute(): int
    {
        return $this->items->sum('quantity');
    }
}
