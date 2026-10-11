<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ScentFamilyResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'key' => $this->key,
            'label' => $this->label,
            'color_from' => $this->color_from,
            'color_to' => $this->color_to,
            'color_bg' => $this->color_bg,
            'image_url' => $this->image_url,
            'sort_order' => $this->sort_order,
            'is_featured' => $this->is_featured,
            'products_count' => (int) ($this->products_count ?? 0),
        ];
    }
}
