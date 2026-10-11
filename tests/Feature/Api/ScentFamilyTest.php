<?php

namespace Tests\Feature\Api;

use App\Models\Product;
use App\Models\ScentFamily;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;
use Tests\Concerns\BuildsShop;
use Tests\TestCase;

class ScentFamilyTest extends TestCase
{
    use BuildsShop;
    use RefreshDatabase;

    public function test_every_product_family_exists_with_defaults_and_live_counts(): void
    {
        $this->product(['fragrance_family' => 'woody']);
        $this->product(['fragrance_family' => 'woody']);
        $this->product(['fragrance_family' => 'woody', 'is_active' => false]); // hidden products don't count
        $this->product(['fragrance_family' => 'woody'])->delete();            // nor deleted ones

        $data = $this->getJson('/api/v1/scent-families')->assertOk()->json('data');

        $this->assertEqualsCanonicalizing(Product::FAMILIES, array_column($data, 'key'));
        $this->assertSame('woody', $data[0]['key']); // previous "Shop by scent" order
        $woody = collect($data)->firstWhere('key', 'woody');
        $this->assertSame(2, $woody['products_count']);
        $this->assertSame('#a2714b', $woody['color_from']);
        $this->assertSame('Amber', collect($data)->firstWhere('key', 'oriental')['label']);
    }

    public function test_admin_edits_a_family(): void
    {
        Storage::fake('public');
        Sanctum::actingAs($this->admin());

        $this->post('/api/v1/admin/scent-families/oud', [
            'label' => '  Oud & Agarwood ',
            'color_from' => '#AA5500',
            'color_to' => '#221100',
            'color_bg' => '#fff8f0',
            'is_featured' => '0',
            'image' => UploadedFile::fake()->image('oud.jpg', 2000, 1500),
        ], ['Accept' => 'application/json'])
            ->assertOk()
            ->assertJsonPath('data.label', 'Oud & Agarwood')
            ->assertJsonPath('data.color_from', '#aa5500')
            ->assertJsonPath('data.is_featured', false);

        $path = ScentFamily::where('key', 'oud')->value('image');
        $this->assertStringEndsWith('.webp', $path);
        [$width] = getimagesizefromstring(Storage::disk('public')->get($path));
        $this->assertSame(1200, $width);

        // JSON toggle keeps the photo; removing it deletes the file.
        $this->patchJson('/api/v1/admin/scent-families/oud', ['is_featured' => true])->assertOk();
        $this->assertSame($path, ScentFamily::where('key', 'oud')->value('image'));
        $this->patchJson('/api/v1/admin/scent-families/oud', ['remove_image' => true])->assertOk()->assertJsonPath('data.image_url', null);
        Storage::disk('public')->assertMissing($path);
    }

    public function test_family_validation(): void
    {
        Sanctum::actingAs($this->admin());

        $this->patchJson('/api/v1/admin/scent-families/oud', ['color_from' => 'red'])->assertUnprocessable()->assertJsonValidationErrors('color_from');
        $this->patchJson('/api/v1/admin/scent-families/oud', ['label' => 'Woody'])->assertUnprocessable()->assertJsonValidationErrors('label');
        $this->patchJson('/api/v1/admin/scent-families/nope', ['label' => 'X'])->assertNotFound();
    }

    public function test_admin_reorders_families(): void
    {
        Sanctum::actingAs($this->admin());
        $keys = ScentFamily::ordered()->pluck('key')->reverse()->values()->all();

        $this->postJson('/api/v1/admin/scent-families/reorder', ['keys' => $keys])->assertOk();
        $this->assertSame($keys, array_column($this->getJson('/api/v1/scent-families')->json('data'), 'key'));

        $this->postJson('/api/v1/admin/scent-families/reorder', ['keys' => ['woody']])->assertUnprocessable();
        $this->postJson('/api/v1/admin/scent-families/reorder', ['keys' => [...array_slice($keys, 1), 'made-up']])->assertUnprocessable();
    }

    public function test_customers_cannot_edit_families(): void
    {
        Sanctum::actingAs($this->customer());

        $this->getJson('/api/v1/admin/scent-families')->assertForbidden();
        $this->patchJson('/api/v1/admin/scent-families/oud', ['label' => 'X'])->assertForbidden();
    }
}
