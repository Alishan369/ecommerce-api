<?php

namespace App\Repositories;

use App\Models\ScentFamily;
use App\Repositories\Interfaces\ScentFamilyRepositoryInterface;
use App\Support\StoredImage;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class ScentFamilyRepository implements ScentFamilyRepositoryInterface
{
    public function allWithProductCount(): Collection
    {
        return ScentFamily::query()->select('scent_families.*')->withProductCount()->ordered()->get();
    }

    public function findByKey(string $key): ScentFamily
    {
        return ScentFamily::where('key', $key)->firstOrFail();
    }

    public function keys(): array
    {
        return ScentFamily::pluck('key')->all();
    }

    public function update(ScentFamily $family, array $data): ScentFamily
    {
        StoredImage::update($family, $data, 'scent-families', ['image' => 1200]);

        return $family;
    }

    public function reorder(array $keys): void
    {
        DB::transaction(function () use ($keys) {
            foreach ($keys as $position => $key) {
                ScentFamily::where('key', $key)->update(['sort_order' => $position]);
            }
        });
    }
}
