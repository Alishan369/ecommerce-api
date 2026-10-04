<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Coupon extends Model
{
    public const TYPE_PERCENT = 'percent';

    public const TYPE_FIXED = 'fixed';

    public const TYPE_FREE_SHIPPING = 'free_shipping';

    public const TYPES = [self::TYPE_PERCENT, self::TYPE_FIXED, self::TYPE_FREE_SHIPPING];

    protected $fillable = [
        'code', 'description', 'type', 'value', 'max_discount', 'min_order_amount',
        'starts_at', 'expires_at', 'usage_limit', 'per_user_limit',
        'first_order_only', 'is_active', 'is_public',
    ];

    protected $casts = [
        'value' => 'decimal:2',
        'max_discount' => 'decimal:2',
        'min_order_amount' => 'decimal:2',
        'starts_at' => 'datetime',
        'expires_at' => 'datetime',
        'usage_limit' => 'integer',
        'per_user_limit' => 'integer',
        'first_order_only' => 'boolean',
        'is_active' => 'boolean',
        'is_public' => 'boolean',
    ];

    /** Codes are case-insensitive for shoppers: always stored and compared upper-case. */
    public static function normalize(?string $code): string
    {
        return strtoupper(preg_replace('/\s+/', '', (string) $code));
    }

    public function setCodeAttribute(?string $value): void
    {
        $this->attributes['code'] = self::normalize($value);
    }

    public function orders(): HasMany
    {
        return $this->hasMany(Order::class);
    }

    /** Orders that count as a use — cancelling an order gives the use back. */
    public function redemptions(): HasMany
    {
        return $this->orders()->where('status', '!=', 'cancelled');
    }

    /** Active and inside its date window right now (usage limits are checked separately). */
    public function scopeLive(Builder $query): Builder
    {
        return $query
            ->where('is_active', true)
            ->where(fn (Builder $q) => $q->whereNull('starts_at')->orWhere('starts_at', '<=', now()))
            ->where(fn (Builder $q) => $q->whereNull('expires_at')->orWhere('expires_at', '>', now()));
    }
}
