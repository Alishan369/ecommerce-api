<?php

namespace App\Repositories\Interfaces;

use App\Models\ScentFamily;
use Illuminate\Support\Collection;

interface ScentFamilyRepositoryInterface
{
    /** Every family in display order, with its live product count. */
    public function allWithProductCount(): Collection;

    public function findByKey(string $key): ScentFamily;

    /** @return list<string> */
    public function keys(): array;

    public function update(ScentFamily $family, array $data): ScentFamily;

    /** @param  list<string>  $keys  every family key, first to last */
    public function reorder(array $keys): void;
}
