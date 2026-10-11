<?php

namespace App\Models;

use App\Support\StoredImage;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/** Home page banners managed from the admin panel (hero slider and promo row). */
class Banner extends Model
{
    public const PLACEMENT_HERO = 'hero';

    public const PLACEMENT_PROMO = 'promo';

    public const PLACEMENTS = [self::PLACEMENT_HERO, self::PLACEMENT_PROMO];

    public const TONES = ['light', 'dark'];

    protected $fillable = [
        'placement', 'eyebrow', 'title', 'subtitle', 'cta_label', 'link_url',
        'image', 'image_mobile', 'video', 'text_tone', 'sort_order', 'is_active', 'starts_at', 'ends_at',
    ];

    protected $casts = [
        'sort_order' => 'integer',
        'is_active' => 'boolean',
        'starts_at' => 'datetime',
        'ends_at' => 'datetime',
    ];

    protected static function booted(): void
    {
        static::deleted(function (Banner $banner) {
            StoredImage::delete($banner->image);
            StoredImage::delete($banner->image_mobile);
            StoredImage::delete($banner->video);
        });
    }

    /** Active and inside its schedule right now. */
    public function scopeLive(Builder $query): Builder
    {
        $now = now();

        return $query
            ->where('is_active', true)
            ->where(fn (Builder $q) => $q->whereNull('starts_at')->orWhere('starts_at', '<=', $now))
            ->where(fn (Builder $q) => $q->whereNull('ends_at')->orWhere('ends_at', '>', $now));
    }

    public function scopeOrdered(Builder $query): Builder
    {
        return $query->orderBy('sort_order')->orderBy('id');
    }

    public function getImageUrlAttribute(): ?string
    {
        return StoredImage::url($this->image);
    }

    public function getImageMobileUrlAttribute(): ?string
    {
        return StoredImage::url($this->image_mobile);
    }

    public function getVideoUrlAttribute(): ?string
    {
        return StoredImage::url($this->video);
    }
}
