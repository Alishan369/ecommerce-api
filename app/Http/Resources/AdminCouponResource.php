<?php

namespace App\Http\Resources;

use App\Services\CouponService;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** A coupon as the admin panel sees it, with usage statistics. */
class AdminCouponResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $uses = (int) ($this->uses ?? 0);

        return [
            'id' => $this->id,
            'code' => $this->code,
            'description' => $this->description,
            'headline' => app(CouponService::class)->headline($this->resource),
            'type' => $this->type,
            'value' => (float) $this->value,
            'max_discount' => $this->max_discount !== null ? (float) $this->max_discount : null,
            'min_order_amount' => (float) $this->min_order_amount,
            'starts_at' => $this->starts_at?->toIso8601String(),
            'expires_at' => $this->expires_at?->toIso8601String(),
            'usage_limit' => $this->usage_limit,
            'per_user_limit' => $this->per_user_limit,
            'first_order_only' => (bool) $this->first_order_only,
            'is_active' => (bool) $this->is_active,
            'is_public' => (bool) $this->is_public,
            'state' => $this->state($uses),
            'stats' => [
                'uses' => $uses,
                'discount_given' => round((float) ($this->discount_given ?? 0), 2),
                'revenue' => round((float) ($this->revenue ?? 0), 2),
            ],
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }

    /** One word for the badge: why a coupon is or isn't usable right now. */
    private function state(int $uses): string
    {
        return match (true) {
            ! $this->is_active => 'inactive',
            $this->expires_at !== null && $this->expires_at->isPast() => 'expired',
            $this->starts_at !== null && $this->starts_at->isFuture() => 'scheduled',
            $this->usage_limit !== null && $uses >= $this->usage_limit => 'used_up',
            default => 'live',
        };
    }
}
