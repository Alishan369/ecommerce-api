<?php

namespace Tests\Feature\Api;

use App\Models\Order;
use App\Models\SiteMedia;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;
use Tests\Concerns\BuildsShop;
use Tests\TestCase;

class MediaAndReportsTest extends TestCase
{
    use BuildsShop;
    use RefreshDatabase;

    /* ---------------------------- site media ---------------------------- */

    public function test_admin_replaces_and_resets_site_media(): void
    {
        Storage::fake('public');
        Sanctum::actingAs($this->admin());

        $this->getJson('/api/v1/media')->assertOk()->assertExactJson(['data' => []]);

        $this->post('/api/v1/admin/media/guide.photo', [
            'image' => UploadedFile::fake()->image('guide.jpg', 2400, 1600),
        ], ['Accept' => 'application/json'])->assertOk();

        $path = SiteMedia::where('slot', 'guide.photo')->value('image');
        $this->assertStringEndsWith('.webp', $path);
        [$width] = getimagesizefromstring(Storage::disk('public')->get($path));
        $this->assertSame(2000, $width);

        $public = $this->getJson('/api/v1/media')->assertOk()->json('data');
        $this->assertSame('guide.photo', $public['guide.photo']['slot']);
        $this->assertStringEndsWith('.webp', $public['guide.photo']['image_url']);

        // Video only where the slot allows it.
        $video = UploadedFile::fake()->create('clip.mp4', 500, 'video/mp4');
        $this->post('/api/v1/admin/media/guide.photo', ['video' => $video], ['Accept' => 'application/json'])
            ->assertUnprocessable()->assertJsonValidationErrors('video');
        $this->post('/api/v1/admin/media/gift.banner', ['video' => $video], ['Accept' => 'application/json'])
            ->assertOk()->assertJsonPath('data.slot', 'gift.banner');
        $this->assertStringEndsWith('.mp4', SiteMedia::where('slot', 'gift.banner')->value('video'));

        $this->post('/api/v1/admin/media/not.a-slot', ['image' => UploadedFile::fake()->image('x.jpg', 800, 800)], ['Accept' => 'application/json'])
            ->assertNotFound();

        $this->deleteJson('/api/v1/admin/media/guide.photo')->assertOk();
        Storage::disk('public')->assertMissing($path);
        $this->assertDatabaseMissing('site_media', ['slot' => 'guide.photo']);
    }

    public function test_customers_cannot_change_media_or_see_reports(): void
    {
        Sanctum::actingAs($this->customer());

        $this->postJson('/api/v1/admin/media/guide.photo', [])->assertForbidden();
        $this->getJson('/api/v1/admin/reports/orders')->assertForbidden();
        $this->get('/api/v1/admin/reports/orders/download')->assertForbidden();
    }

    /* ------------------------------ reports ----------------------------- */

    private function seedOrders(): void
    {
        $product = $this->product(['name' => '=Rose Attar', 'sku' => 'RA-1', 'stock' => 20]);
        $this->placeCodOrder($this->customer(['name' => 'Asha']), $product, 2);
        $cancelled = $this->placeCodOrder($this->customer(['name' => 'Ben']), $product, 1);
        Order::where('order_number', $cancelled['order_number'])->update(['status' => 'cancelled']);
    }

    public function test_every_report_previews_and_downloads(): void
    {
        $this->seedOrders();
        Sanctum::actingAs($this->admin());
        $today = now()->toDateString();

        foreach (['orders', 'sales', 'products', 'inventory', 'customers', 'coupons'] as $type) {
            $preview = $this->getJson("/api/v1/admin/reports/{$type}?from={$today}&to={$today}")->assertOk();
            $this->assertNotEmpty($preview->json('columns'), $type);
            $this->assertNotEmpty($preview->json('summary'), $type);

            $csv = $this->get("/api/v1/admin/reports/{$type}/download?from={$today}&to={$today}")->assertOk();
            $this->assertStringContainsString('text/csv', $csv->headers->get('Content-Type'));
            $body = $csv->streamedContent();
            $this->assertStringStartsWith("\xEF\xBB\xBF", $body, $type);
            $this->assertSame(1 + $preview->json('total_rows'), count(array_filter(explode("\n", trim(substr($body, 3))))), $type);
        }
    }

    public function test_report_figures_exclude_cancelled_orders(): void
    {
        $this->seedOrders();
        Sanctum::actingAs($this->admin());
        $today = now()->toDateString();

        $sales = $this->getJson("/api/v1/admin/reports/sales?from={$today}&to={$today}")->assertOk();
        // 2 × ₹800 = ₹1,600 (+ shipping, if any) from the one non-cancelled order.
        $row = $sales->json('rows.0');
        $this->assertSame(1, $row[1]); // orders
        $this->assertSame(2, $row[2]); // units
        $this->assertSame(1, $row[7]); // cancelled

        $products = $this->getJson("/api/v1/admin/reports/products?from={$today}&to={$today}")->assertOk();
        $this->assertSame(2, $products->json('rows.0.3'));

        $orders = $this->getJson("/api/v1/admin/reports/orders?from={$today}&to={$today}&status=cancelled")->assertOk();
        $this->assertSame(1, $orders->json('total_rows'));

        // Out of range → empty.
        $this->getJson('/api/v1/admin/reports/orders?from=2020-01-01&to=2020-01-31')->assertOk()->assertJsonPath('total_rows', 0);
    }

    public function test_reports_download_as_excel_and_print_view_takes_more_rows(): void
    {
        $this->seedOrders();
        Sanctum::actingAs($this->admin());
        $today = now()->toDateString();

        $response = $this->get("/api/v1/admin/reports/orders/download?format=xlsx&from={$today}&to={$today}")->assertOk();
        $this->assertStringContainsString('spreadsheetml', $response->headers->get('Content-Type'));
        $this->assertStringContainsString('.xlsx', $response->headers->get('Content-Disposition'));

        $zip = new \ZipArchive;
        $this->assertTrue($zip->open($response->getFile()->getPathname()));
        $sheet = $zip->getFromName('xl/worksheets/sheet1.xml');
        $zip->close();
        $this->assertStringContainsString('Order number', $sheet);
        $this->assertSame(3, substr_count($sheet, '<row ')); // header + 2 orders

        $this->getJson('/api/v1/admin/reports/orders?limit=500')->assertOk();
        $this->getJson('/api/v1/admin/reports/orders?limit=99999')->assertUnprocessable();
        $this->get('/api/v1/admin/reports/orders/download?format=pdf', ['Accept' => 'application/json'])->assertUnprocessable();
    }

    public function test_storefront_media_seeder_fills_gaps_only(): void
    {
        Storage::fake('public');
        \App\Models\Category::factory()->create(['name' => 'Women', 'parent_id' => null, 'image' => null]);
        $custom = \App\Models\Category::factory()->create(['name' => 'Men', 'parent_id' => null, 'image' => 'https://cdn.example.com/men.jpg']);

        $this->seed(\Database\Seeders\StorefrontMediaSeeder::class);

        $this->assertGreaterThan(0, \App\Models\Banner::where('placement', 'hero')->count());
        $this->assertSame(count(SiteMedia::SLOTS), SiteMedia::count());
        $this->assertNotNull(\App\Models\Category::where('name', 'Women')->value('image'));
        $this->assertSame('https://cdn.example.com/men.jpg', $custom->fresh()->image); // admin's photo untouched
        $video = SiteMedia::where('slot', 'gift.banner')->value('video');
        Storage::disk('public')->assertExists($video);
        $this->assertSame(0, \App\Models\ScentFamily::whereNull('image')->count()); // every family gets a photo

        // Re-running adds nothing.
        $counts = [\App\Models\Banner::count(), SiteMedia::count()];
        $this->seed(\Database\Seeders\StorefrontMediaSeeder::class);
        $this->assertSame($counts, [\App\Models\Banner::count(), SiteMedia::count()]);
    }

    public function test_csv_neutralises_formula_cells_and_validates_input(): void
    {
        $this->seedOrders();
        Sanctum::actingAs($this->admin());

        $body = $this->get('/api/v1/admin/reports/inventory/download')->assertOk()->streamedContent();
        $this->assertStringContainsString("'=Rose Attar", $body);

        $this->getJson('/api/v1/admin/reports/nope')->assertNotFound();
        $this->getJson('/api/v1/admin/reports/orders?from=2026-02-10&to=2026-02-01')->assertUnprocessable()->assertJsonValidationErrors('to');
        $this->getJson('/api/v1/admin/reports/orders?status=lost')->assertUnprocessable();
    }
}
