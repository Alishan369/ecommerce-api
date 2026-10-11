<?php

namespace Tests\Feature\Api;

use App\Models\Category;
use App\Models\Product;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Testing\TestResponse;
use Tests\Concerns\BuildsShop;
use Tests\TestCase;

class DemoGuardTest extends TestCase
{
    use BuildsShop, RefreshDatabase;

    private function uploadProductImage(): TestResponse
    {
        Storage::fake('public');
        $category = Category::factory()->create();

        return $this->actingAs($this->admin(), 'sanctum')->post('/api/v1/admin/products', [
            'category_id' => $category->id,
            'name' => 'Velvet Oud',
            'price' => 1299,
            'stock' => 10,
            'image' => UploadedFile::fake()->image('bottle.jpg', 800, 800),
        ], ['Accept' => 'application/json']);
    }

    public function test_demo_blocks_uploads_by_default(): void
    {
        config(['shop.demo.enabled' => true]);

        $this->uploadProductImage()->assertUnprocessable()->assertJsonValidationErrors('demo');
    }

    public function test_demo_accepts_uploads_when_allowed(): void
    {
        config(['shop.demo.enabled' => true, 'shop.demo.allow_uploads' => true]);

        $id = $this->uploadProductImage()->assertCreated()->json('data.id');

        Storage::disk('public')->assertExists(Product::find($id)->image);
    }

    public function test_allowing_uploads_still_blocks_deletes(): void
    {
        config(['shop.demo.enabled' => true, 'shop.demo.allow_uploads' => true]);

        $this->actingAs($this->admin(), 'sanctum')
            ->deleteJson('/api/v1/admin/products/'.$this->product()->id)
            ->assertUnprocessable()->assertJsonValidationErrors('demo');
    }
}
