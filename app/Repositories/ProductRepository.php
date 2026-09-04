<?php

namespace App\Repositories;

use App\Models\Product;
use App\Repositories\Interfaces\ProductRepositoryInterface;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;

class ProductRepository implements ProductRepositoryInterface
{
    private const DEFAULT_PER_PAGE = 20;

    private const MAX_PER_PAGE = 100;

    public function getAllProducts(array $filters = []): LengthAwarePaginator
    {
        $perPage = $this->resolvePerPage($filters['per_page'] ?? null);
        $categorySlug = $filters['category'] ?? null;
        $search = $filters['search'] ?? null;
        $sort = $filters['sort'] ?? 'latest';

        $query = Product::query()
            ->with('category:id,name,slug')
            ->where('is_active', true)
            ->when(
                $categorySlug,
                fn (Builder $q) => $q->whereHas(
                    'category',
                    fn (Builder $c) => $c->where('slug', $categorySlug)
                )
            )
            ->when(
                $search,
                fn (Builder $q) => $q->where('name', 'like', '%'.$search.'%')
            );

        $this->applySort($query, $sort);

        return $query->paginate($perPage)->withQueryString();
    }

    public function store(array $data): Product
    {
        return Product::create($data);
    }

    public function update(Product $product, array $data): Product
    {
        $product->update($data);

        return $product->fresh();
    }

    public function delete(Product $product): bool
    {
        return (bool) $product->delete();
    }

    public function findBySlug(string $slug): Product
    {
        return Product::query()
            ->with('category.parent')
            ->where('slug', $slug)
            ->firstOrFail();
    }

    private function resolvePerPage(?int $requested): int
    {
        if ($requested === null || $requested < 1) {
            return self::DEFAULT_PER_PAGE;
        }

        return min($requested, self::MAX_PER_PAGE);
    }

    private function applySort(Builder $query, string $sort): void
    {
        match ($sort) {
            'price_asc' => $query->orderByRaw('COALESCE(sale_price, price) asc')->orderBy('id'),
            'price_desc' => $query->orderByRaw('COALESCE(sale_price, price) desc')->orderBy('id'),
            'name_asc' => $query->orderBy('name')->orderBy('id'),
            default => $query->latest()->orderBy('id', 'desc'),
        };
    }
}
