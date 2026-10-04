<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Carbon;

/** A customer as seen by the admin panel (includes order aggregates). */
class CustomerResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'email' => $this->email,
            'phone' => $this->phone,
            'orders_count' => (int) ($this->orders_count ?? 0),
            'active_orders_count' => (int) ($this->active_orders_count ?? 0),
            'total_spent' => round((float) ($this->total_spent ?? 0), 2),
            'last_order_at' => $this->last_order_at ? Carbon::parse($this->last_order_at)->toIso8601String() : null,
            'joined_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
