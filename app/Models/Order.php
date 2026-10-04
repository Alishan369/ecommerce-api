<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

class Order extends Model
{
    public const STATUSES = ['pending', 'confirmed', 'processing', 'shipped', 'delivered', 'cancelled'];

    protected $fillable = [
        'user_id', 'guest_token', 'order_number', 'email',
        'shipping_name', 'shipping_phone', 'shipping_address_line',
        'shipping_city', 'shipping_state', 'shipping_pincode',
        'subtotal', 'shipping_amount', 'discount_amount', 'coupon_id', 'coupon_code', 'total',
        'status', 'payment_method', 'payment_status', 'notes', 'placed_at',
    ];

    protected $casts = [
        'subtotal' => 'decimal:2',
        'shipping_amount' => 'decimal:2',
        'discount_amount' => 'decimal:2',
        'total' => 'decimal:2',
        'placed_at' => 'datetime',
    ];

    public function items()
    {
        return $this->hasMany(OrderItem::class);
    }

    public function payments()
    {
        return $this->hasMany(Payment::class);
    }

    public function coupon()
    {
        return $this->belongsTo(Coupon::class);
    }

    public function isOnlinePayment(): bool
    {
        return $this->payment_method !== 'cod';
    }

    /** An online order that can still be paid (not paid, not expired, not cancelled). */
    public function awaitingPayment(): bool
    {
        return $this->isOnlinePayment()
            && $this->status === 'pending'
            && ! in_array($this->payment_status, ['paid', 'expired'], true);
    }

    /** When an unpaid online order will be cancelled and its stock released. */
    public function paymentDueAt(): ?Carbon
    {
        return $this->awaitingPayment() && $this->placed_at
            ? $this->placed_at->copy()->addMinutes((int) config('services.razorpay.unpaid_order_ttl', 30))
            : null;
    }

    /** Order total in the smallest currency unit, as payment gateways expect. */
    public function amountInPaise(): int
    {
        return (int) round((float) $this->total * 100);
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
