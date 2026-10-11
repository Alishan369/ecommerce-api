<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class BannerResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $admin = $request->routeIs('v1.admin.*');

        return [
            'id' => $this->id,
            'placement' => $this->placement,
            'eyebrow' => $this->eyebrow,
            'title' => $this->title,
            'subtitle' => $this->subtitle,
            'cta_label' => $this->cta_label,
            'link_url' => $this->link_url,
            'image_url' => $this->image_url,
            'image_mobile_url' => $this->image_mobile_url,
            'video_url' => $this->video_url,
            'text_tone' => $this->text_tone,
            'sort_order' => $this->sort_order,
            // Scheduling details only matter to the admin panel.
            'is_active' => $this->when($admin, $this->is_active),
            'starts_at' => $this->when($admin, fn () => $this->starts_at?->toIso8601String()),
            'ends_at' => $this->when($admin, fn () => $this->ends_at?->toIso8601String()),
        ];
    }
}
