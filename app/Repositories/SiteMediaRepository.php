<?php

namespace App\Repositories;

use App\Models\SiteMedia;
use App\Repositories\Interfaces\SiteMediaRepositoryInterface;
use App\Support\StoredImage;
use Illuminate\Support\Collection;

class SiteMediaRepository implements SiteMediaRepositoryInterface
{
    // A video needs a still to show while it loads (and for reduced motion) — the photo.
    private const FIELDS = ['image' => 2000, 'video' => null];

    public function all(): Collection
    {
        return SiteMedia::query()->whereIn('slot', SiteMedia::SLOTS)->get();
    }

    public function save(string $slot, array $data): ?SiteMedia
    {
        $media = SiteMedia::firstOrNew(['slot' => $slot]);

        if (! $media->exists) {
            foreach (self::FIELDS as $field => $maxSize) {
                $data = StoredImage::fromInput($data, 'site-media', $field, $maxSize);
            }
            $media->fill($data)->save();
        } else {
            StoredImage::update($media, $data, 'site-media', self::FIELDS);
        }

        // Nothing left in the slot → back to the storefront default.
        if (! $media->image && ! $media->video) {
            $media->delete();

            return null;
        }

        return $media->fresh();
    }

    public function reset(string $slot): void
    {
        SiteMedia::where('slot', $slot)->first()?->delete();
    }
}
