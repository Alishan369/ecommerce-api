<?php

namespace Tests\Feature\Api;

use App\Models\Banner;
use App\Models\Category;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;
use Tests\Concerns\BuildsShop;
use Tests\TestCase;

class BannerTest extends TestCase
{
    use BuildsShop;
    use RefreshDatabase;

    private function banner(array $attrs = []): Banner
    {
        return Banner::create([
            'placement' => 'hero',
            'title' => 'Hello',
            'image' => 'banners/x.webp',
            ...$attrs,
        ]);
    }

    public function test_public_list_shows_only_live_banners_in_order(): void
    {
        $this->banner(['title' => 'Second', 'sort_order' => 2]);
        $this->banner(['title' => 'First', 'sort_order' => 1]);
        $this->banner(['title' => 'Off', 'is_active' => false]);
        $this->banner(['title' => 'Later', 'starts_at' => now()->addDay()]);
        $this->banner(['title' => 'Over', 'ends_at' => now()->subMinute()]);
        $this->banner(['title' => 'Promo', 'placement' => 'promo']);

        $response = $this->getJson('/api/v1/banners?placement=hero')->assertOk();

        $this->assertSame(['First', 'Second'], array_column($response->json('data'), 'title'));
        // Scheduling details stay in the admin panel.
        $response->assertJsonMissingPath('data.0.is_active');

        $this->getJson('/api/v1/banners?placement=nope')->assertUnprocessable();
    }

    public function test_admin_creates_banner_with_compressed_webp_images(): void
    {
        Storage::fake('public');
        Sanctum::actingAs($this->admin());

        $response = $this->postJson('/api/v1/admin/banners', [
            'placement' => 'hero',
            'title' => 'The gift edit',
            'cta_label' => 'Shop gifts',
            'link_url' => '/collections/gift-sets',
            'image' => UploadedFile::fake()->image('wide.jpg', 3000, 1250),
            'image_mobile' => UploadedFile::fake()->image('tall.png', 1600, 2000),
        ])->assertCreated();

        $banner = Banner::firstOrFail();
        foreach (['image' => 2000, 'image_mobile' => 1350] as $field => $max) {
            $path = $banner->{$field};
            $this->assertStringEndsWith('.webp', $path);
            Storage::disk('public')->assertExists($path);
            [$width, $height] = getimagesizefromstring(Storage::disk('public')->get($path));
            $this->assertLessThanOrEqual($max, max($width, $height));
        }

        $response->assertJsonPath('data.title', 'The gift edit')
            ->assertJsonPath('data.is_active', true);
        $this->assertNotNull($response->json('data.image_mobile_url'));
    }

    public function test_banner_validation(): void
    {
        Storage::fake('public');
        Sanctum::actingAs($this->admin());

        $this->postJson('/api/v1/admin/banners', ['placement' => 'hero', 'title' => 'No image'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('image');

        $this->postJson('/api/v1/admin/banners', [
            'placement' => 'hero',
            'title' => 'Bad link',
            'link_url' => 'javascript:alert(1)',
            'image' => UploadedFile::fake()->image('a.jpg', 1200, 500),
        ])->assertUnprocessable()->assertJsonValidationErrors('link_url');

        $this->postJson('/api/v1/admin/banners', [
            'placement' => 'hero',
            'title' => 'Too small',
            'image' => UploadedFile::fake()->image('a.jpg', 300, 200),
        ])->assertUnprocessable()->assertJsonValidationErrors('image');
    }

    public function test_updating_and_deleting_cleans_up_old_files(): void
    {
        Storage::fake('public');
        Sanctum::actingAs($this->admin());

        $this->postJson('/api/v1/admin/banners', [
            'placement' => 'promo',
            'title' => 'Original',
            'image' => UploadedFile::fake()->image('a.jpg', 1200, 900),
            'image_mobile' => UploadedFile::fake()->image('b.jpg', 800, 1000),
        ])->assertCreated();
        $banner = Banner::firstOrFail();
        [$oldImage, $oldMobile] = [$banner->image, $banner->image_mobile];

        $this->postJson("/api/v1/admin/banners/{$banner->id}", [
            '_method' => 'PUT',
            'title' => 'Renamed',
            'image' => UploadedFile::fake()->image('c.jpg', 1200, 900),
            'remove_image_mobile' => true,
        ])->assertOk()->assertJsonPath('data.title', 'Renamed')->assertJsonPath('data.image_mobile_url', null);

        Storage::disk('public')->assertMissing($oldImage);
        Storage::disk('public')->assertMissing($oldMobile);
        $newImage = $banner->fresh()->image;
        Storage::disk('public')->assertExists($newImage);

        // Partial JSON update (e.g. the active toggle) keeps the image.
        $this->patchJson("/api/v1/admin/banners/{$banner->id}", ['is_active' => false])->assertOk();
        $this->assertSame($newImage, $banner->fresh()->image);

        $this->deleteJson("/api/v1/admin/banners/{$banner->id}")->assertOk();
        Storage::disk('public')->assertMissing($newImage);
        $this->assertDatabaseCount('banners', 0);
    }

    public function test_customers_cannot_manage_banners(): void
    {
        Sanctum::actingAs($this->customer());

        $this->getJson('/api/v1/admin/banners')->assertForbidden();
        $this->postJson('/api/v1/admin/banners', ['title' => 'x'])->assertForbidden();
    }

    public function test_optimize_command_converts_existing_uploads(): void
    {
        Storage::fake('public');
        $png = UploadedFile::fake()->image('old.png', 2400, 1600);
        $old = $png->storeAs('categories', 'old.png', 'public');
        $category = Category::factory()->create(['image' => $old]);

        $this->artisan('images:optimize', ['--dry-run' => true])->assertSuccessful();
        $this->assertSame($old, $category->fresh()->image);

        $this->artisan('images:optimize')->assertSuccessful();

        $new = $category->fresh()->image;
        $this->assertSame('categories/old.webp', $new);
        Storage::disk('public')->assertExists($new);
        Storage::disk('public')->assertMissing($old);
    }

    public function test_product_and_category_uploads_are_compressed(): void
    {
        Storage::fake('public');
        Sanctum::actingAs($this->admin());

        $this->post('/api/v1/admin/categories', [
            'name' => 'Oud',
            'image' => UploadedFile::fake()->image('cat.png', 1800, 1200),
        ], ['Accept' => 'application/json'])->assertCreated();

        $path = Category::firstOrFail()->image;
        $this->assertStringEndsWith('.webp', $path);
        [$width] = getimagesizefromstring(Storage::disk('public')->get($path));
        $this->assertSame(1400, $width);
    }
}
