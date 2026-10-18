<?php

namespace App\Repositories;

use App\Models\Banner;
use App\Repositories\Interfaces\BannerRepositoryInterface;
use App\Support\StoredImage;
use Illuminate\Support\Collection;

class BannerRepository implements BannerRepositoryInterface
{
    /** Longest side for banner images (sharp on large screens, small on disk); the video is stored as uploaded. */
    private const IMAGE_FIELDS = ['image' => 2000, 'image_mobile' => 1350, 'video' => null];

    public function all(): Collection
    {
        return Banner::query()->orderBy('placement')->ordered()->get();
    }

    public function live(?string $placement = null, int $limit = 12): Collection
    {
        return Banner::query()
            ->live()
            ->when($placement, fn ($q, $placement) => $q->where('placement', $placement))
            ->ordered()
            ->limit($limit)
            ->get();
    }

    public function store(array $data): Banner
    {
        foreach (self::IMAGE_FIELDS as $field => $maxSize) {
            $data = StoredImage::fromInput($data, 'banners', $field, $maxSize);
        }

        return Banner::create($data)->fresh();
    }

    public function update(Banner $banner, array $data): Banner
    {
        StoredImage::update($banner, $data, 'banners', self::IMAGE_FIELDS);

        return $banner->fresh();
    }

    public function delete(Banner $banner): void
    {
        $banner->delete();
    }
}
