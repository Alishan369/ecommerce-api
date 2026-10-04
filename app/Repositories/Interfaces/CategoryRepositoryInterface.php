<?php

namespace App\Repositories\Interfaces;

use App\Models\Category;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;

interface CategoryRepositoryInterface
{
    public function getAllCategories(array $filters = []): LengthAwarePaginator;

    public function getTree(bool $includeInactive = false): Collection;

    public function findBySlug(string $slug): Category;

    public function findActiveBySlug(string $slug): Category;

    public function store(array $data): Category;

    public function update(string $slug, array $data): Category;

    public function delete(string $slug, string $onChildren = 'reject'): bool;
}
