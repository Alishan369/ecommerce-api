<?php

namespace Tests\Feature\Api;

use App\Mail\OrderPlaced;
use App\Mail\OrderStatusUpdated;
use App\Models\Cart;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Laravel\Sanctum\Sanctum;
use Tests\Concerns\BuildsShop;
use Tests\TestCase;

class CartCheckoutTest extends TestCase
{
    use BuildsShop, RefreshDatabase;

    private const GUEST = ['X-Cart-Session-Id' => 'cart_0b9e1c2a-3d4f-4a5b-8c6d-7e8f9a0b1c2d'];

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
            ->assertJsonPath('data.subtotal', 1600)
            ->assertJsonPath('data.shipping_amount', 0)
            ->assertJsonPath('data.total', 1600);

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
        $user = $this->customer();

        $this->postJson('/api/v1/cart/items', ['product_id' => $p->id, 'quantity' => 2], self::GUEST)->assertOk();

        $token = $user->createToken('t')->plainTextToken;
        $this->withToken($token)->getJson('/api/v1/cart', self::GUEST)
            ->assertOk()->assertJsonPath('data.item_count', 2);

        $this->assertSame($user->id, Cart::query()->sole()->user_id);
    }

    public function test_checkout_and_order_pages_require_sign_in(): void
    {
        $p = $this->product();
        $this->postJson('/api/v1/cart/items', ['product_id' => $p->id, 'quantity' => 1], self::GUEST)->assertOk();

        $this->postJson('/api/v1/orders', $this->shipping(), self::GUEST)->assertUnauthorized();
        $this->getJson('/api/v1/orders', self::GUEST)->assertUnauthorized();
        $this->getJson('/api/v1/orders/ORD-20261001-ABCDEFGH', self::GUEST)->assertUnauthorized();
        $this->assertDatabaseCount('orders', 0);
    }

    public function test_checkout_charges_server_price_reserves_stock_saves_address_and_emails(): void
    {
        Mail::fake();
        $p = $this->product(['stock' => 5]);
        $user = $this->customer();

        $order = $this->placeCodOrder($user, $p, 2, ['save_address' => true]);

        $this->assertSame('1600.00', $order['total']);
        $this->assertSame('0.00', $order['shipping_amount']);
        $this->assertSame($user->email, $order['email']);
        $this->assertSame('9876543210', $order['shipping_address']['phone']); // "+91 98765 43210" normalised
        $this->assertSame('pending', $order['status']);

        $this->assertSame(3, $p->fresh()->stock);
        $this->getJson('/api/v1/cart')->assertJsonPath('data.item_count', 0);
        $this->assertDatabaseHas('addresses', ['user_id' => $user->id, 'pincode' => '560038', 'is_default' => true]);

        Mail::assertQueued(OrderPlaced::class, fn (OrderPlaced $mail) => $mail->hasTo($user->email)
            && $mail->order->order_number === $order['order_number']);

        // The owner can read it; nobody else can (404, not 403 — order numbers aren't confirmed to exist).
        $this->getJson('/api/v1/orders/'.$order['order_number'])->assertOk();
        $this->getJson('/api/v1/orders')->assertOk()->assertJsonPath('data.0.order_number', $order['order_number']);

        Sanctum::actingAs($this->customer());
        $this->getJson('/api/v1/orders/'.$order['order_number'])->assertNotFound();

        // Public "track your order" needs the number AND the email it was placed with.
        $this->postJson('/api/v1/orders/lookup', ['order_number' => strtolower($order['order_number']), 'email' => strtoupper($user->email)])
            ->assertOk()->assertJsonPath('data.order_number', $order['order_number']);
        $this->postJson('/api/v1/orders/lookup', ['order_number' => $order['order_number'], 'email' => 'x@example.com'])
            ->assertNotFound();
    }

    public function test_shipping_is_charged_below_the_free_shipping_threshold(): void
    {
        $p = $this->product(['price' => 300, 'sale_price' => 249]);
        Sanctum::actingAs($this->customer());

        $this->postJson('/api/v1/cart/items', ['product_id' => $p->id, 'quantity' => 1])
            ->assertOk()
            ->assertJsonPath('data.shipping_amount', 49)
            ->assertJsonPath('data.total', 298)
            ->assertJsonPath('data.amount_to_free_shipping', 250);

        $this->postJson('/api/v1/orders', $this->shipping())
            ->assertCreated()
            ->assertJsonPath('data.shipping_amount', '49.00')
            ->assertJsonPath('data.total', '298.00');
    }

    public function test_customer_can_cancel_before_shipping_and_stock_returns(): void
    {
        Mail::fake();
        $p = $this->product(['stock' => 5]);
        $order = $this->placeCodOrder($this->customer(), $p, 2);

        $this->postJson("/api/v1/orders/{$order['order_number']}/cancel")
            ->assertOk()
            ->assertJsonPath('data.status', 'cancelled')
            ->assertJsonPath('data.can_cancel', false);

        $this->assertSame(5, $p->fresh()->stock);
        Mail::assertQueued(OrderStatusUpdated::class, fn (OrderStatusUpdated $mail) => $mail->order->status === 'cancelled');

        $this->postJson("/api/v1/orders/{$order['order_number']}/cancel")->assertStatus(422);
    }

    public function test_checkout_validation(): void
    {
        Sanctum::actingAs($this->customer());

        $this->postJson('/api/v1/orders', $this->shipping())
            ->assertStatus(422)->assertJsonPath('errors.cart.0', 'Your cart is empty.');

        // Razorpay isn't configured in tests, so online payment isn't an accepted method.
        $this->postJson('/api/v1/orders', $this->shipping([
            'shipping_phone' => '12345', 'shipping_pincode' => '012345', 'payment_method' => 'razorpay',
        ]))->assertStatus(422)->assertJsonValidationErrors(['shipping_phone', 'shipping_pincode', 'payment_method']);
    }

    public function test_stock_is_rechecked_at_checkout(): void
    {
        $p = $this->product(['stock' => 2]);
        $user = $this->customer();
        Sanctum::actingAs($user);
        $this->postJson('/api/v1/cart/items', ['product_id' => $p->id, 'quantity' => 2])->assertOk();

        // Someone else buys one meanwhile.
        $p->update(['stock' => 1]);

        $this->postJson('/api/v1/orders', $this->shipping())
            ->assertStatus(422)
            ->assertJsonPath('errors.cart.0', "{$p->name} has only 1 item(s) available.");
        $this->assertSame(1, $p->fresh()->stock);
        $this->assertNull(User::find($user->id)->orders()->first());
    }
}
