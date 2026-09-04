<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class CartResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'session_token' => $this->session_token,
            'items' => $this->items->map(fn ($item) => [
                'product_id' => $item->product_id,
                'name' => $item->product->name,
                'slug' => $item->product->slug,
                'price' => $item->price,
                'quantity' => $item->quantity,
                'subtotal' => $item->subtotal,
            ]),
            'total' => $this->total,
            'item_count' => $this->item_count,
        ];
    }
}
