<?php

namespace App\Repositories\Interfaces;

use App\Models\Banner;
use Illuminate\Support\Collection;

interface BannerRepositoryInterface
{
    /** Every banner for the admin list, grouped by placement. */
    public function all(): Collection;

    /** Live banners for the storefront, optionally for one placement. */
    public function live(?string $placement = null, int $limit = 12): Collection;

    public function store(array $data): Banner;

    public function update(Banner $banner, array $data): Banner;

    public function delete(Banner $banner): void;
}
