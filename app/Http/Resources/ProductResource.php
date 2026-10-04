<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ProductResource extends JsonResource
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
            'category_id' => $this->category_id,
            'name' => $this->name,
            'slug' => $this->slug,
            'sku' => $this->sku,
            'description' => $this->description,
            'short_description' => $this->short_description,
            'image_url' => $this->image_url,
            'gender' => $this->gender,
            'concentration' => $this->concentration,
            'size_ml' => $this->size_ml,
            'fragrance_family' => $this->fragrance_family,
            'notes' => [
                'top' => $this->notes['top'] ?? [],
                'heart' => $this->notes['heart'] ?? [],
                'base' => $this->notes['base'] ?? [],
            ],
            'price' => (float) $this->price,
            'sale_price' => $this->sale_price !== null ? (float) $this->sale_price : null,
            // What the customer pays — checkout uses the same COALESCE(sale_price, price).
            'final_price' => (float) ($this->sale_price ?? $this->price),
            'discount_percent' => $this->discount_percent,
            'stock' => (int) $this->stock,
            'in_stock' => $this->stock > 0,
            'is_active' => (bool) $this->is_active,
            'is_featured' => (bool) $this->is_featured,
            'is_bestseller' => (bool) $this->is_bestseller,
            'category' => new CategoryResource($this->whenLoaded('category')),
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
