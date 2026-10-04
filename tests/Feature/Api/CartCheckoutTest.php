<?php

namespace Tests\Feature\Api;

use App\Models\Cart;
use App\Models\Category;
use App\Models\Product;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CartCheckoutTest extends TestCase
{
    use RefreshDatabase;

    private const GUEST = ['X-Cart-Session-Id' => 'cart_0b9e1c2a-3d4f-4a5b-8c6d-7e8f9a0b1c2d'];

    private function product(array $attrs = []): Product
    {
        return Product::factory()->create([
            'category_id' => Category::factory()->create()->id,
            'price' => 1000,
            'sale_price' => 800,
            'stock' => 5,
            'is_active' => true,
            ...$attrs,
        ]);
    }

    private function address(array $overrides = []): array
    {
        return [
            'email' => 'guest@example.com',
            'shipping_name' => 'Ravi Kumar',
            'shipping_phone' => '+91 98765 43210',
            'shipping_address_line' => '12 MG Road, Indiranagar',
            'shipping_city' => 'Bengaluru',
            'shipping_state' => 'Karnataka',
            'shipping_pincode' => '560038',
            'payment_method' => 'cod',
            ...$overrides,
        ];
    }

    public function test_reading_cart_never_creates_rows(): void
    {
        $this->getJson('/api/v1/cart')->assertOk()->assertJsonPath('data.item_count', 0);
        $this->getJson('/api/v1/cart', self::GUEST)->assertOk();
        $this->getJson('/api/v1/cart', ['X-Cart-Session-Id' => 'garbage'])->assertOk();

        $this->assertDatabaseCount('carts', 0);
    }

    public function test_guest_cart_persists_across_requests_with_session_header(): void
    {
        $p = $this->product();

        $this->postJson('/api/v1/cart/items', ['product_id' => $p->id, 'quantity' => 2], self::GUEST)
            ->assertOk()
            ->assertJsonPath('data.items.0.product_id', $p->id)
            ->assertJsonPath('data.items.0.unit_price', 800)
            ->assertJsonPath('data.items.0.mrp', 1000)
            ->assertJsonPath('data.subtotal', 1600);

        $this->getJson('/api/v1/cart', self::GUEST)->assertJsonPath('data.item_count', 2);

        $this->patchJson("/api/v1/cart/items/{$p->id}", ['quantity' => 3], self::GUEST)
            ->assertOk()->assertJsonPath('data.item_count', 3);

        $this->patchJson("/api/v1/cart/items/{$p->id}", ['quantity' => 6], self::GUEST)
            ->assertStatus(422)->assertJsonPath('errors.quantity.0', 'Only 5 left in stock.');

        $this->deleteJson("/api/v1/cart/items/{$p->id}", [], self::GUEST)
            ->assertOk()->assertJsonPath('data.item_count', 0);

        $this->assertDatabaseCount('carts', 1);
    }

    public function test_adding_without_session_or_inactive_product_fails_cleanly(): void
    {
        $inactive = $this->product(['is_active' => false]);

        $this->postJson('/api/v1/cart/items', ['product_id' => $inactive->id, 'quantity' => 1])->assertStatus(422);
        $this->postJson('/api/v1/cart/items', ['product_id' => $inactive->id, 'quantity' => 1], self::GUEST)
            ->assertStatus(422)->assertJsonPath('errors.product_id.0', 'This product is no longer available.');
    }

    public function test_guest_cart_merges_into_user_cart_on_sign_in(): void
    {
        $p = $this->product();
        $user = User::factory()->create();

        $this->postJson('/api/v1/cart/items', ['product_id' => $p->id, 'quantity' => 2], self::GUEST)->assertOk();

        $token = $user->createToken('t')->plainTextToken;
        $this->withToken($token)->getJson('/api/v1/cart', self::GUEST)
            ->assertOk()->assertJsonPath('data.item_count', 2);

        $this->assertSame($user->id, Cart::query()->sole()->user_id);
    }

    public function test_guest_checkout_creates_order_charges_server_price_and_reduces_stock(): void
    {
        $p = $this->product(['stock' => 5]);
        $this->postJson('/api/v1/cart/items', ['product_id' => $p->id, 'quantity' => 2], self::GUEST)->assertOk();

        $order = $this->postJson('/api/v1/orders', $this->address(), self::GUEST)
            ->assertCreated()
            ->assertJsonPath('data.total', '1600.00')
            ->assertJsonPath('data.shipping_address.phone', '9876543210')
            ->assertJsonPath('data.status', 'pending')
            ->json('data');

        $this->assertSame(3, $p->fresh()->stock);
        $this->getJson('/api/v1/cart', self::GUEST)->assertJsonPath('data.item_count', 0);

        // The same guest session can read the confirmation; another session cannot.
        $this->getJson('/api/v1/orders/'.$order['order_number'], self::GUEST)->assertOk();
        $this->getJson('/api/v1/orders/'.$order['order_number'], ['X-Cart-Session-Id' => 'cart_ffffffff-ffff-4fff-8fff-ffffffffffff'])
            ->assertNotFound();

        // Lookup by number + email.
        $this->postJson('/api/v1/orders/lookup', ['order_number' => strtolower($order['order_number']), 'email' => 'GUEST@example.com'])
            ->assertOk()->assertJsonPath('data.order_number', $order['order_number']);
        $this->postJson('/api/v1/orders/lookup', ['order_number' => $order['order_number'], 'email' => 'x@example.com'])
            ->assertNotFound();
    }

    public function test_checkout_validation(): void
    {
        $this->postJson('/api/v1/orders', $this->address(), self::GUEST)
            ->assertStatus(422)->assertJsonPath('errors.cart.0', 'Your cart is empty.');

        $this->postJson('/api/v1/orders', $this->address([
            'email' => null, 'shipping_phone' => '12345', 'shipping_pincode' => '012345', 'payment_method' => 'razorpay',
        ]), self::GUEST)
            ->assertStatus(422)
            ->assertJsonValidationErrors(['email', 'shipping_phone', 'shipping_pincode', 'payment_method']);
    }
}
