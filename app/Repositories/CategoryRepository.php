<?php

namespace App\Repositories;

use App\Models\Category;
use App\Repositories\Interfaces\CategoryRepositoryInterface;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class CategoryRepository implements CategoryRepositoryInterface
{
    private const DEFAULT_PER_PAGE = 20;

    private const MAX_PER_PAGE = 100;

    public function getAllCategories(array $filters = []): LengthAwarePaginator
    {
        $perPage = $this->resolvePerPage($filters['per_page'] ?? null);
        $search = $filters['search'] ?? null;
        $isActive = $filters['is_active'] ?? null;
        $parentId = array_key_exists('parent_id', $filters) ? $filters['parent_id'] : false;

        return Category::query()
            ->withCount(['children', 'products'])
            ->with('parent:id,name,slug')
            ->when($search, function (Builder $q) use ($search) {
                $escaped = addcslashes($search, '%_\\');
                $q->where('name', 'like', '%'.$escaped.'%');
            })
            ->when(! is_null($isActive), fn (Builder $q) => $q->where('is_active', $isActive))
            // false = filter not sent at all; null = explicitly "top-level only"
            ->when($parentId !== false, fn (Builder $q) => $q->where('parent_id', $parentId))
            ->orderBy('sort_order')->orderBy('name')
            ->paginate($perPage)
            ->withQueryString();
    }

    /**
     * Full active tree for public nav / mega-menu — not paginated,
     * categories rarely number in the thousands.
     */
    public function getTree(bool $includeInactive = false): Collection
    {
        $categories = Category::query()
            ->when(! $includeInactive, fn (Builder $q) => $q->where('is_active', true))
            ->withCount([
                'products' => fn (Builder $q) => $includeInactive ? $q : $q->where('is_active', true),
            ])
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get(['id', 'name', 'slug', 'description', 'image', 'is_active', 'sort_order', 'parent_id']);

        return $this->buildTree($categories);
    }

    public function findActiveBySlug(string $slug): Category
    {
        return Category::query()
            ->where('slug', $slug)
            ->where('is_active', true)
            ->with([
                'parent:id,name,slug,parent_id',
                'children' => fn ($q) => $q->where('is_active', true)->orderBy('sort_order')->orderBy('name'),
            ])
            ->firstOrFail();
    }

    public function store(array $data): Category
    {
        if (! empty($data['parent_id'])) {
            $this->guardParentExists($data['parent_id']);
        }

        $image = $data['image'] ?? null;

        return Category::create([
            'name' => $data['name'],
            'description' => $data['description'] ?? null,
            'is_active' => $data['is_active'] ?? true,
            'sort_order' => $data['sort_order'] ?? 0,
            'slug' => $data['slug'] ?? null,
            'parent_id' => $data['parent_id'] ?? null,
            'image' => $image instanceof UploadedFile ? $image->store('categories', 'public') : null,
        ]);
    }

    public function update(string $slug, array $data): Category
    {
        $category = $this->findBySlug($slug);

        if (array_key_exists('parent_id', $data) && $data['parent_id']) {
            $this->guardParentExists($data['parent_id']);
            $this->guardNoCycle($category, (int) $data['parent_id']);
        }

        $previousImage = $category->image;
        $image = $data['image'] ?? null;

        if ($image instanceof UploadedFile) {
            $data['image'] = $image->store('categories', 'public');
        } elseif (! empty($data['remove_image'])) {
            $data['image'] = null;
        } else {
            unset($data['image']);
        }
        unset($data['remove_image']);

        $category->update($data);

        if (array_key_exists('image', $data) && $previousImage && $previousImage !== $category->image
            && ! Str::startsWith($previousImage, ['http://', 'https://'])) {
            Storage::disk('public')->delete($previousImage);
        }

        return $category->refresh();
    }

    /**
     * @param  'reject'|'reparent'|'cascade'  $onChildren  what to do if this
     *                                                     category has children: reject the delete, promote children to
     *                                                     root (parent_id = null), or delete children too.
     */
    public function delete(string $slug, string $onChildren = 'reject'): bool
    {
        $category = $this->findBySlug($slug);

        if ($category->products()->exists()) {
            throw ValidationException::withMessages([
                'category' => 'Cannot delete a category that still has products. Move or delete them first.',
            ]);
        }

        $childCount = $category->children()->count();

        if ($childCount > 0) {
            match ($onChildren) {
                'reparent' => $category->children()->update(['parent_id' => $category->parent_id]),
                'cascade' => $category->children()->each(
                    fn (Category $child) => $this->delete($child->slug, 'cascade')
                ),
                default => throw ValidationException::withMessages([
                    'category' => "Cannot delete: {$childCount} subcategories still reference this category.",
                ]),
            };
        }

        return (bool) $category->delete();
    }

    public function findBySlug(string $slug): Category
    {
        return Category::query()
            ->with('parent:id,name,slug')
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

    private function guardParentExists(int $parentId): void
    {
        if (! Category::whereKey($parentId)->exists()) {
            throw ValidationException::withMessages([
                'parent_id' => 'Selected parent category does not exist.',
            ]);
        }
    }

    /**
     * Walk up from the proposed new parent; if we ever hit $category's own id,
     * the move would create a cycle.
     */
    private function guardNoCycle(Category $category, int $newParentId): void
    {
        if ($newParentId === $category->id) {
            throw ValidationException::withMessages([
                'parent_id' => 'A category cannot be its own parent.',
            ]);
        }

        $current = Category::find($newParentId);
        $depth = 0;

        while ($current && $depth < 50) { // depth cap guards against pre-existing bad data
            if ($current->id === $category->id) {
                throw ValidationException::withMessages([
                    'parent_id' => 'Cannot set parent: this would create a circular category tree.',
                ]);
            }

            $current = $current->parent;
            $depth++;
        }
    }

    private function buildTree(Collection $categories, ?int $parentId = null): Collection
    {
        return $categories
            ->where('parent_id', $parentId)
            ->values()
            ->map(function (Category $category) use ($categories) {
                $children = $this->buildTree($categories, $category->id);

                $category->setRelation('children', $children);
                $category->setAttribute(
                    'total_products_count',
                    (int) $category->products_count + (int) $children->sum('total_products_count')
                );

                return $category;
            });
    }
}
