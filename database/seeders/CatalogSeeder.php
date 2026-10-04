<?php

namespace Database\Seeders;

use App\Models\Category;
use App\Models\Product;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

/**
 * Seeds the collections → subcategories → products tree from data/catalog.php.
 * Idempotent: categories are matched by slug and products by SKU.
 */
class CatalogSeeder extends Seeder
{
    public function run(): void
    {
        $tree = require __DIR__.'/data/catalog.php';
        $products = 0;

        foreach ($tree as $rootIndex => $root) {
            $parent = Category::withTrashed()->updateOrCreate(
                ['slug' => $root['slug']],
                [
                    'name' => $root['name'],
                    'description' => $root['description'],
                    'is_active' => true,
                    'sort_order' => ($rootIndex + 1) * 10,
                    'parent_id' => null,
                ],
            );
            $this->restore($parent);

            foreach ($root['children'] as $childIndex => $child) {
                $subcategory = Category::withTrashed()->updateOrCreate(
                    ['slug' => $child['slug']],
                    [
                        'name' => $child['name'],
                        'description' => $child['description'],
                        'is_active' => true,
                        'sort_order' => ($childIndex + 1) * 10,
                        'parent_id' => $parent->id,
                    ],
                );
                $this->restore($subcategory);

                foreach ($child['products'] as $index => $item) {
                    $this->seedProduct($subcategory, $child['code'], $index + 1, $item);
                    $products++;
                }
            }
        }

        $this->command?->info('Catalogue ready: '.count($tree).' collections, '.$products.' products.');
    }

    private function seedProduct(Category $category, string $code, int $position, array $item): void
    {
        $sku = sprintf('SJ-%s-%03d', $code, $position);

        $product = Product::withTrashed()->updateOrCreate(
            ['sku' => $sku],
            [
                'category_id' => $category->id,
                'name' => $item['name'],
                'slug' => Str::slug($item['name']),
                'short_description' => $item['short'],
                'description' => $this->describe($item),
                'gender' => $item['gender'],
                'concentration' => $item['type'],
                'size_ml' => $item['ml'],
                'fragrance_family' => $item['family'],
                'notes' => ['top' => $item['top'], 'heart' => $item['heart'], 'base' => $item['base']],
                'price' => $item['mrp'],
                'sale_price' => $item['price'] < $item['mrp'] ? $item['price'] : null,
                'stock' => $item['stock'],
                'is_active' => true,
                'is_featured' => $item['featured'] ?? false,
                'is_bestseller' => $item['bestseller'] ?? false,
            ],
        );
        $this->restore($product);
    }

    /** Re-running the seeder brings back seeded rows an admin had soft-deleted. */
    private function restore(Category|Product $model): void
    {
        if ($model->trashed()) {
            $model->restore();
        }
    }

    private function describe(array $item): string
    {
        $list = fn (array $notes) => Str::lower(implode(' and ', $notes));
        $longevity = match ($item['type']) {
            'Attar' => 'As a pure perfume oil, a few dabs on the pulse points last well beyond a day.',
            'EDP' => 'An eau de parfum with 20%+ fragrance oil, it lasts 8–10 hours on skin.',
            'EDT' => 'A lighter eau de toilette, ideal for daytime and humid weather.',
            default => 'Light enough to reapply through the day.',
        };

        return "{$item['short']} It opens with {$list($item['top'])}, settles into a heart of "
            ."{$list($item['heart'])}, and dries down to {$list($item['base'])}. {$longevity}";
    }
}
