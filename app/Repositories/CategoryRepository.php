<?php

namespace App\Repositories;

use App\Models\Category;
use App\Repositories\Interfaces\CategoryRepositoryInterface;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

class CategoryRepository implements CategoryRepositoryInterface
{
    public function getAllCategories(array $filters = []): LengthAwarePaginator
    {
        $perPage = $filters['per_page'] ?? 20;
        $search = $filters['search'] ?? null;
        $isActive = $filters['is_active'] ?? null;

        return Category::query()
            ->when($search, fn ($q) => $q->where('name', 'like', '%'.$search.'%'))
            ->when(! is_null($isActive), fn ($q) => $q->where('is_active', $isActive))
            ->latest()
            ->paginate($perPage);
    }

    public function store(array $data): Category
    {
        return Category::create([
            'name' => $data['name'],
            'description' => $data['description'] ?? null,
            'is_active' => $data['is_active'] ?? true,
            'slug' => $data['slug'] ?? null,
        ]);
    }

    public function update(string $slug, array $data): Category
    {
        $category = Category::findBySlug($slug);

        $category->update($data);

        return $category->refresh();
    }

    public function delete(string $slug): bool
    {
        $category = Category::findBySlug($slug);

        return $category->delete();
    }
}
