<?php

namespace App\Repositories\Interfaces;

use App\Models\SiteMedia;
use Illuminate\Support\Collection;

interface SiteMediaRepositoryInterface
{
    /** Media for every known slot that has been replaced by an admin. */
    public function all(): Collection;

    /**
     * Create or update the media in a slot.
     *
     * @return SiteMedia|null null when nothing is left in the slot (it went back to the default)
     */
    public function save(string $slot, array $data): ?SiteMedia;

    /** Reset a slot to the storefront default. */
    public function reset(string $slot): void;
}
