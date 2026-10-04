<?php

namespace Tests\Feature\Api;

use App\Models\Category;
use App\Models\Product;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CatalogTest extends TestCase
{
    use RefreshDatabase;

    private function product(Category $category, array $attrs = []): Product
    {
        return Product::factory()->create([
            'category_id' => $category->id,
            'price' => 1000,
            'sale_price' => null,
            'stock' => 5,
            'is_active' => true,
            'is_featured' => false, // factory randomises this
            'is_bestseller' => false,
            ...$attrs,
        ]);
    }

    public function test_tree_returns_children_and_rolled_up_counts(): void
    {
        $men = Category::factory()->create(['name' => 'Men', 'slug' => 'men']);
        $woody = Category::factory()->create(['name' => 'Woody', 'slug' => 'men-woody', 'parent_id' => $men->id]);
        Category::factory()->create(['name' => 'Hidden', 'slug' => 'hidden', 'is_active' => false]);

        $this->product($men);
        $this->product($woody);
        $this->product($woody);
        $this->product($woody, ['is_active' => false]); // not counted

        $this->getJson('/api/v1/categories/tree')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.slug', 'men')
            ->assertJsonPath('data.0.products_count', 1)
            ->assertJsonPath('data.0.total_products_count', 3)
            ->assertJsonPath('data.0.children.0.slug', 'men-woody')
            ->assertJsonPath('data.0.children.0.parent_id', $men->id)
            ->assertJsonPath('data.0.children.0.total_products_count', 2);
    }

    public function test_category_show_exists(): void
    {
        $men = Category::factory()->create(['slug' => 'men']);
        Category::factory()->create(['slug' => 'men-fresh', 'parent_id' => $men->id]);

        $this->getJson('/api/v1/categories/men')
            ->assertOk()
            ->assertJsonPath('data.slug', 'men')
            ->assertJsonPath('data.children.0.slug', 'men-fresh');

        $this->getJson('/api/v1/categories/nope')
            ->assertNotFound()
            ->assertExactJson(['message' => 'Not found.']);
    }

    public function test_parent_category_filter_includes_subcategory_products(): void
    {
        $men = Category::factory()->create(['slug' => 'men']);
        $woody = Category::factory()->create(['slug' => 'men-woody', 'parent_id' => $men->id]);
        $women = Category::factory()->create(['slug' => 'women']);

        $a = $this->product($men);
        $b = $this->product($woody);
        $this->product($women);

        $ids = collect($this->getJson('/api/v1/products?category=men')->assertOk()->json('data'))->pluck('id');

        $this->assertEqualsCanonicalizing([$a->id, $b->id], $ids->all());
        $this->getJson('/api/v1/products?category=unknown')->assertOk()->assertJsonCount(0, 'data');
    }

    public function test_filters_and_sorts(): void
    {
        $cat = Category::factory()->create();
        $cheap = $this->product($cat, ['price' => 500, 'sale_price' => 400, 'is_bestseller' => true]);
        $mid = $this->product($cat, ['price' => 1500, 'stock' => 0, 'is_featured' => true]);
        $dear = $this->product($cat, ['price' => 3000, 'sku' => 'OUD-777']);

        $ids = fn (string $qs) => collect($this->getJson('/api/v1/products?'.$qs)->assertOk()->json('data'))->pluck('id')->all();

        $this->assertSame([$cheap->id, $mid->id, $dear->id], $ids('sort=price_asc'));
        $this->assertSame([$dear->id, $mid->id, $cheap->id], $ids('sort=price_desc'));
        $this->assertSame([$mid->id], $ids('min_price=1000&max_price=2000'));
        $this->assertEqualsCanonicalizing([$cheap->id, $dear->id], $ids('in_stock=1'));
        $this->assertSame([$cheap->id], $ids('on_sale=1'));
        $this->assertSame([$cheap->id], $ids('bestseller=1'));
        $this->assertSame([$mid->id], $ids('featured=1'));
        $this->assertSame([$dear->id], $ids('search=oud-7'));
        $this->assertSame($mid->id, $ids('sort=featured')[0]);
    }

    public function test_product_resource_shape(): void
    {
        $cat = Category::factory()->create(['slug' => 'men']);
        $p = $this->product($cat, ['price' => 1000, 'sale_price' => 700, 'image' => 'https://cdn.example.com/a.webp']);

        $this->getJson('/api/v1/products/'.$p->slug)
            ->assertOk()
            ->assertJsonPath('data.final_price', 700)
            ->assertJsonPath('data.discount_percent', 30)
            ->assertJsonPath('data.in_stock', true)
            ->assertJsonPath('data.image_url', 'https://cdn.example.com/a.webp')
            ->assertJsonPath('data.category.slug', 'men');
    }

    public function test_invalid_filters_return_422_not_500(): void
    {
        $this->getJson('/api/v1/products?sort=bogus')->assertStatus(422);
        $this->getJson('/api/v1/products?per_page[]=1')->assertStatus(422);
    }
}
