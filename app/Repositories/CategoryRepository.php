<?php

namespace App\Repositories;

use App\Models\Category;
use App\Repositories\Interfaces\CategoryRepositoryInterface;
use App\Support\StoredImage;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class CategoryRepository implements CategoryRepositoryInterface
{
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

    /** The parent was already checked by the request (exists, not deleted). */
    public function store(array $data): Category
    {
        return Category::create(StoredImage::fromInput($data, 'categories'));
    }

    public function update(string $slug, array $data): Category
    {
        $category = $this->findBySlug($slug);

        if (! empty($data['parent_id'])) {
            $this->guardNoCycle($category, (int) $data['parent_id']);
        }

        StoredImage::update($category, $data, 'categories');

        return $category->refresh();
    }

    /**
     * @param  'reject'|'reparent'|'cascade'  $onChildren  what to do if this category has children:
     *                                                     reject the delete, move them up a level, or delete them too.
     */
    public function delete(string $slug, string $onChildren = 'reject'): bool
    {
        // All or nothing: a cascade that reaches a category with products must not leave half a branch deleted.
        return DB::transaction(function () use ($slug, $onChildren) {
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
        });
    }

    public function findBySlug(string $slug): Category
    {
        return Category::query()
            ->with('parent:id,name,slug')
            ->where('slug', $slug)
            ->firstOrFail();
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
