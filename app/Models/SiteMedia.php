<?php

namespace App\Models;

use App\Support\StoredImage;
use Illuminate\Database\Eloquent\Model;

/**
 * Admin-replaceable photo (and optional video) for a fixed spot on the storefront.
 * A slot with no row shows the storefront's built-in default.
 */
class SiteMedia extends Model
{
    protected $table = 'site_media';

    /**
     * Every replaceable spot. Keep in sync with the frontend's SITE_MEDIA_SLOTS.
     * (Scent-family photos live on the families themselves — see ScentFamily.)
     */
    public const SLOTS = [
        'gift.banner', 'login.panel', 'about.story', 'about.cta', 'contact.panel',
        'guide.photo', 'menu.promo', 'shop.header', 'offers.header',
    ];

    /** Slots that may hold a video as well as a photo. */
    public const VIDEO_SLOTS = ['gift.banner', 'login.panel'];

    protected $fillable = ['slot', 'image', 'video'];

    protected static function booted(): void
    {
        static::deleted(function (SiteMedia $media) {
            StoredImage::delete($media->image);
            StoredImage::delete($media->video);
        });
    }

    public function toPublicArray(): array
    {
        return [
            'slot' => $this->slot,
            'image_url' => StoredImage::url($this->image),
            'video_url' => StoredImage::url($this->video),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
