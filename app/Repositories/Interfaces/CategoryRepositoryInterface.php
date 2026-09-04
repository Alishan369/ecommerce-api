<?php

namespace App\Repositories\Interfaces;

use Illuminate\Contracts\Pagination\LengthAwarePaginator;

interface CategoryRepositoryInterface
{
    public function getAllCategories(array $filters = []): LengthAwarePaginator;

    public function store(array $data);

    public function update(string $slug, array $data);

    public function delete(string $slug): bool;
}
