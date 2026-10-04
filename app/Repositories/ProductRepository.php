<?php

namespace App\Repositories;

use App\Models\Category;
use App\Models\Product;
use App\Repositories\Interfaces\ProductRepositoryInterface;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class ProductRepository implements ProductRepositoryInterface
{
    private const DEFAULT_PER_PAGE = 20;

    private const MAX_PER_PAGE = 100;

    private const MAX_CATEGORY_DEPTH = 10;

    /** Public storefront listing — active products only. */
    public function getAllProducts(array $filters = []): LengthAwarePaginator
    {
        $query = Product::query()
            ->with('category:id,name,slug,parent_id')
            ->where('is_active', true);

        $this->applyFilters($query, $filters, activeCategoriesOnly: true);
        $this->applySort($query, $filters['sort'] ?? 'latest');

        return $query->paginate($this->resolvePerPage($filters['per_page'] ?? null))->withQueryString();
    }

    /** Admin listing — includes inactive products. */
    public function getAdminProducts(array $filters = []): LengthAwarePaginator
    {
        $query = Product::query()
            ->with('category:id,name,slug,parent_id')
            ->when(($filters['status'] ?? null) === 'active', fn (Builder $q) => $q->where('is_active', true))
            ->when(($filters['status'] ?? null) === 'inactive', fn (Builder $q) => $q->where('is_active', false));

        $this->applyFilters($query, $filters, activeCategoriesOnly: false);
        $this->applySort($query, $filters['sort'] ?? 'latest');

        return $query->paginate($this->resolvePerPage($filters['per_page'] ?? null))->withQueryString();
    }

    public function store(array $data): Product
    {
        return Product::create($this->withStoredImage($data))->load('category');
    }

    public function update(Product $product, array $data): Product
    {
        $previousImage = $product->image;
        $data = $this->withStoredImage($data);

        $product->update($data);

        if (array_key_exists('image', $data) && $previousImage !== $product->image) {
            $this->deleteLocalImage($previousImage);
        }

        return $product->fresh('category');
    }

    public function delete(Product $product): bool
    {
        // Soft delete — the image is kept so the product can be restored.
        return (bool) $product->delete();
    }

    public function findBySlug(string $slug): Product
    {
        return Product::query()
            ->with([
                'category:id,name,slug,parent_id',
                'category.parent:id,name,slug,parent_id',
            ])
            ->where('slug', $slug)
            ->where('is_active', true)
            ->firstOrFail();
    }

    private function applyFilters(Builder $query, array $filters, bool $activeCategoriesOnly): void
    {
        $categorySlug = $filters['category'] ?? null;
        $search = $filters['search'] ?? null;

        $query
            ->when($categorySlug, fn (Builder $q) => $q->whereIn(
                'category_id',
                $this->categoryIdsWithDescendants($categorySlug, $activeCategoriesOnly)
            ))
            ->when($search, function (Builder $q) use ($search) {
                $like = '%'.addcslashes($search, '%_\\').'%';

                $q->where(fn (Builder $w) => $w
                    ->where('name', 'like', $like)
                    ->orWhere('sku', 'like', $like)
                    // "rose", "oud", "vanilla" — shoppers search by note as often as by name.
                    ->orWhere('notes', 'like', $like)
                    ->orWhere('fragrance_family', 'like', $like)
                    ->orWhereHas('category', fn (Builder $c) => $c->where('name', 'like', $like))
                );
            })
            ->when($filters['gender'] ?? null, fn (Builder $q, string $gender) => $q->where('gender', $gender))
            ->when($filters['family'] ?? null, fn (Builder $q, string $family) => $q->where('fragrance_family', $family))
            ->when(isset($filters['min_price']), fn (Builder $q) => $q->whereRaw('COALESCE(sale_price, price) >= CAST(? AS DECIMAL(10,2))', [(float) $filters['min_price']]))
            ->when(isset($filters['max_price']), fn (Builder $q) => $q->whereRaw('COALESCE(sale_price, price) <= CAST(? AS DECIMAL(10,2))', [(float) $filters['max_price']]))
            ->when(! empty($filters['in_stock']), fn (Builder $q) => $q->where('stock', '>', 0))
            ->when(! empty($filters['featured']), fn (Builder $q) => $q->where('is_featured', true))
            ->when(! empty($filters['bestseller']), fn (Builder $q) => $q->where('is_bestseller', true))
            ->when(! empty($filters['on_sale']), fn (Builder $q) => $q->whereNotNull('sale_price')->whereColumn('sale_price', '<', 'price'))
            ->when(! empty($filters['exclude']), fn (Builder $q) => $q->whereKeyNot($filters['exclude']));
    }

    /**
     * A category page shows products from the category and all of its subcategories,
     * so "Men" includes "Men › Woody".
     *
     * @return array<int>
     */
    private function categoryIdsWithDescendants(string $slug, bool $activeOnly): array
    {
        $categories = Category::query()
            ->when($activeOnly, fn (Builder $q) => $q->where('is_active', true))
            ->get(['id', 'parent_id', 'slug']);

        $root = $categories->firstWhere('slug', $slug);

        if (! $root) {
            return [0]; // unknown/inactive category → no products, rather than ignoring the filter
        }

        $ids = [$root->id];
        $frontier = [$root->id];

        for ($depth = 0; $frontier && $depth < self::MAX_CATEGORY_DEPTH; $depth++) {
            $frontier = $categories->whereIn('parent_id', $frontier)->pluck('id')->all();
            $ids = [...$ids, ...$frontier];
        }

        return $ids;
    }

    private function resolvePerPage(?int $requested): int
    {
        if ($requested === null || $requested < 1) {
            return self::DEFAULT_PER_PAGE;
        }

        return min($requested, self::MAX_PER_PAGE);
    }

    private function applySort(Builder $query, ?string $sort): void
    {
        match ($sort) {
            'price_asc' => $query->orderByRaw('COALESCE(sale_price, price) asc')->orderBy('id'),
            'price_desc' => $query->orderByRaw('COALESCE(sale_price, price) desc')->orderBy('id'),
            'name_asc' => $query->orderBy('name')->orderBy('id'),
            'stock_asc' => $query->orderBy('stock')->orderBy('name'), // admin restock view
            'featured' => $query->orderByDesc('is_featured')->orderByDesc('is_bestseller')->latest()->orderBy('id', 'desc'),
            'discount' => $query
                ->orderByRaw('CASE WHEN sale_price IS NULL OR price = 0 THEN 0 ELSE (price - sale_price) / price END DESC')
                ->orderBy('id', 'desc'),
            default => $query->latest()->orderBy('id', 'desc'), // 'latest' | 'newest'
        };
    }

    /**
     * Replaces an uploaded file with its stored path, honours `remove_image`,
     * and leaves `image` untouched when neither was sent.
     */
    private function withStoredImage(array $data): array
    {
        $image = $data['image'] ?? null;

        if ($image instanceof UploadedFile) {
            $data['image'] = $image->store('products', 'public');
        } elseif (! empty($data['remove_image'])) {
            $data['image'] = null;
        } else {
            unset($data['image']);
        }

        unset($data['remove_image']);

        return $data;
    }

    private function deleteLocalImage(?string $path): void
    {
        if ($path && ! Str::startsWith($path, ['http://', 'https://'])) {
            Storage::disk('public')->delete($path);
        }
    }
}
