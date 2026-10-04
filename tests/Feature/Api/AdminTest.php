<?php

namespace Tests\Feature\Api;

use App\Models\Category;
use App\Models\Order;
use App\Models\Product;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class AdminTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        return User::factory()->create(['role' => 'admin']);
    }

    public function test_admin_routes_require_admin_role(): void
    {
        $this->getJson('/api/v1/admin/products')->assertUnauthorized();

        $this->actingAs(User::factory()->create(['role' => 'customer']), 'sanctum')
            ->getJson('/api/v1/admin/products')->assertForbidden();

        $this->actingAs($this->admin(), 'sanctum')
            ->getJson('/api/v1/admin/products')->assertOk();
    }

    public function test_admin_can_create_subcategory_and_rename_without_unique_name_clash(): void
    {
        $this->actingAs($this->admin(), 'sanctum');

        $men = $this->postJson('/api/v1/admin/categories', ['name' => 'Men', 'slug' => 'men'])->assertCreated()->json('data');
        $women = $this->postJson('/api/v1/admin/categories', ['name' => 'Women', 'slug' => 'women'])->assertCreated()->json('data');

        $this->postJson('/api/v1/admin/categories', ['name' => 'Fresh', 'slug' => 'men-fresh', 'parent_id' => $men['id']])
            ->assertCreated()->assertJsonPath('data.parent_id', $men['id']);
        $this->postJson('/api/v1/admin/categories', ['name' => 'Fresh', 'slug' => 'women-fresh', 'parent_id' => $women['id']])
            ->assertCreated();

        // Re-saving with the same slug and name must not trip the unique rule.
        $this->putJson('/api/v1/admin/categories/men-fresh', ['name' => 'Fresh', 'slug' => 'men-fresh', 'is_active' => false])
            ->assertOk()->assertJsonPath('data.is_active', false);

        $this->getJson('/api/v1/admin/categories/tree')->assertOk()->assertJsonPath('data.0.children.0.is_active', false);
    }

    public function test_admin_product_crud_with_image_and_inactive_listing(): void
    {
        Storage::fake('public');
        $this->actingAs($this->admin(), 'sanctum');
        $cat = Category::factory()->create();

        $product = $this->post('/api/v1/admin/products', [
            'category_id' => $cat->id,
            'name' => 'Velvet Oud',
            'price' => 1299,
            'sale_price' => 999,
            'stock' => 10,
            'is_active' => '0',
            'is_bestseller' => '1',
            'image' => UploadedFile::fake()->image('bottle.jpg', 800, 800),
        ], ['Accept' => 'application/json'])->assertCreated()->json('data');

        $this->assertTrue($product['is_bestseller']);
        $this->assertNotNull($product['image_url']);
        Storage::disk('public')->assertExists(Product::find($product['id'])->image);

        // Inactive products appear in the admin list but not on the storefront.
        $this->getJson('/api/v1/admin/products?status=inactive')->assertJsonPath('data.0.id', $product['id']);
        $this->getJson('/api/v1/products')->assertJsonCount(0, 'data');

        // Multipart update via method spoofing, removing the image.
        $this->post("/api/v1/admin/products/{$product['id']}", [
            '_method' => 'PUT', 'price' => 1299, 'is_active' => '1', 'remove_image' => '1',
        ], ['Accept' => 'application/json'])->assertOk()->assertJsonPath('data.image_url', null)->assertJsonPath('data.is_active', true);

        $this->deleteJson("/api/v1/admin/products/{$product['id']}")->assertOk();
        $this->assertSoftDeleted('products', ['id' => $product['id']]);
    }

    public function test_admin_sees_all_orders_and_cancelling_restocks(): void
    {
        $product = Product::factory()->create(['category_id' => Category::factory()->create()->id, 'stock' => 3]);
        $order = Order::create([
            'order_number' => 'ORD-20261001-ABCDEFGH', 'email' => 'c@example.com',
            'shipping_name' => 'C', 'shipping_phone' => '9876543210', 'shipping_address_line' => 'Somewhere 1',
            'shipping_city' => 'Pune', 'shipping_state' => 'Maharashtra', 'shipping_pincode' => '411001',
            'subtotal' => 100, 'total' => 100, 'status' => 'pending', 'payment_method' => 'cod', 'placed_at' => now(),
        ]);
        $order->items()->create([
            'product_id' => $product->id, 'product_name' => $product->name, 'product_sku' => $product->sku,
            'unit_price' => 100, 'quantity' => 2, 'subtotal' => 200,
        ]);

        $this->actingAs($this->admin(), 'sanctum');

        $this->getJson('/api/v1/admin/orders')->assertOk()->assertJsonPath('data.0.order_number', 'ORD-20261001-ABCDEFGH');

        $this->patchJson("/api/v1/admin/orders/{$order->id}", ['status' => 'cancelled'])
            ->assertOk()->assertJsonPath('data.status', 'cancelled');
        $this->assertSame(5, $product->fresh()->stock);

        $this->patchJson("/api/v1/admin/orders/{$order->id}", ['status' => 'pending'])->assertStatus(422);
    }
}
