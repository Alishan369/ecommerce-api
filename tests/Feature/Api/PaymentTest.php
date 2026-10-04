<?php

namespace Tests\Feature\Api;

use App\Models\Category;
use App\Models\Order;
use App\Models\Payment;
use App\Models\Product;
use App\Services\Payments\RazorpayGateway;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class PaymentTest extends TestCase
{
    use RefreshDatabase;

    private const GUEST = ['X-Cart-Session-Id' => 'cart_aaaaaaaa-bbbb-4ccc-8ddd-eeeeeeeeeeee'];

    private const SECRET = 'test_key_secret';

    private const WEBHOOK_SECRET = 'test_webhook_secret';

    private Product $product;

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'services.razorpay.key_id' => 'rzp_test_key',
            'services.razorpay.key_secret' => self::SECRET,
            'services.razorpay.webhook_secret' => self::WEBHOOK_SECRET,
            'services.razorpay.unpaid_order_ttl' => 30,
        ]);

        Http::preventStrayRequests();
        Http::fake([
            'api.razorpay.com/v1/orders' => Http::sequence()
                ->push(['id' => 'order_RZP0001', 'amount' => 160000, 'currency' => 'INR', 'status' => 'created'])
                ->push(['id' => 'order_RZP0002', 'amount' => 160000, 'currency' => 'INR', 'status' => 'created']),
        ]);

        $this->product = Product::factory()->create([
            'category_id' => Category::factory()->create()->id,
            'price' => 1000, 'sale_price' => 800, 'stock' => 5, 'is_active' => true,
        ]);
    }

    private function placeOnlineOrder(): array
    {
        $this->postJson('/api/v1/cart/items', ['product_id' => $this->product->id, 'quantity' => 2], self::GUEST)->assertOk();

        return $this->postJson('/api/v1/orders', [
            'email' => 'buyer@example.com',
            'shipping_name' => 'Meera Iyer',
            'shipping_phone' => '9876543210',
            'shipping_address_line' => '4 Lake View Road, Adyar',
            'shipping_city' => 'Chennai',
            'shipping_state' => 'Tamil Nadu',
            'shipping_pincode' => '600020',
            'payment_method' => 'razorpay',
        ], self::GUEST)->assertCreated()->json();
    }

    private function signature(string $orderId, string $paymentId): string
    {
        return hash_hmac('sha256', $orderId.'|'.$paymentId, self::SECRET);
    }

    private function webhook(array $event, ?string $signature = null)
    {
        $body = json_encode($event);

        return $this->call('POST', '/api/v1/payments/razorpay/webhook', [], [], [], [
            'CONTENT_TYPE' => 'application/json',
            'HTTP_ACCEPT' => 'application/json',
            'HTTP_X_RAZORPAY_SIGNATURE' => $signature ?? hash_hmac('sha256', $body, self::WEBHOOK_SECRET),
        ], $body);
    }

    public function test_methods_list_online_first_when_configured(): void
    {
        $this->getJson('/api/v1/payments/methods')->assertOk()
            ->assertJsonPath('data.0.code', 'razorpay')
            ->assertJsonPath('data.1.code', 'cod');
    }

    public function test_methods_fall_back_to_cod_without_keys(): void
    {
        config(['services.razorpay.key_id' => null]);
        $this->app->forgetInstance(RazorpayGateway::class);

        $this->getJson('/api/v1/payments/methods')->assertOk()->assertJsonCount(1, 'data')->assertJsonPath('data.0.code', 'cod');
    }

    public function test_online_checkout_creates_gateway_order_for_db_total(): void
    {
        $body = $this->placeOnlineOrder();

        $this->assertSame('pending', $body['data']['status']);
        $this->assertTrue($body['data']['can_pay']);
        $this->assertNotNull($body['data']['payment_due_at']);
        $this->assertSame('rzp_test_key', $body['payment']['key_id']);
        $this->assertSame('order_RZP0001', $body['payment']['order_id']);
        $this->assertSame(160000, $body['payment']['amount']);
        $this->assertArrayNotHasKey('key_secret', $body['payment']);

        Http::assertSent(fn ($request) => $request->url() === 'https://api.razorpay.com/v1/orders'
            && $request['amount'] === 160000
            && $request['receipt'] === $body['data']['order_number']
            && $request->hasHeader('Authorization', 'Basic '.base64_encode('rzp_test_key:'.self::SECRET)));

        $this->assertSame(3, $this->product->fresh()->stock); // reserved while awaiting payment
    }

    public function test_verify_marks_paid_and_is_idempotent(): void
    {
        $order = $this->placeOnlineOrder()['data'];
        $payload = [
            'order_number' => $order['order_number'],
            'razorpay_order_id' => 'order_RZP0001',
            'razorpay_payment_id' => 'pay_ABC123',
            'razorpay_signature' => $this->signature('order_RZP0001', 'pay_ABC123'),
        ];

        $this->postJson('/api/v1/payments/razorpay/verify', $payload, self::GUEST)
            ->assertOk()
            ->assertJsonPath('data.payment_status', 'paid')
            ->assertJsonPath('data.status', 'confirmed')
            ->assertJsonPath('data.can_pay', false);

        $this->postJson('/api/v1/payments/razorpay/verify', $payload, self::GUEST)->assertOk();
        $this->assertDatabaseCount('payments', 1);
        $this->assertDatabaseHas('payments', ['gateway_payment_id' => 'pay_ABC123', 'status' => 'paid']);
    }

    public function test_verify_rejects_bad_signature_and_foreign_sessions(): void
    {
        $order = $this->placeOnlineOrder()['data'];
        $payload = [
            'order_number' => $order['order_number'],
            'razorpay_order_id' => 'order_RZP0001',
            'razorpay_payment_id' => 'pay_ABC123',
            'razorpay_signature' => 'forged',
        ];

        $this->postJson('/api/v1/payments/razorpay/verify', $payload, self::GUEST)->assertStatus(422);

        $payload['razorpay_signature'] = $this->signature('order_RZP0001', 'pay_ABC123');
        $this->postJson('/api/v1/payments/razorpay/verify', $payload, ['X-Cart-Session-Id' => 'cart_ffffffff-ffff-4fff-8fff-ffffffffffff'])
            ->assertNotFound();

        $this->assertSame('pending', Order::sole()->payment_status);
    }

    public function test_webhook_captures_payment_and_checks_signature_and_amount(): void
    {
        $this->placeOnlineOrder();
        $event = fn (int $amount) => ['event' => 'payment.captured', 'payload' => ['payment' => ['entity' => [
            'id' => 'pay_WEB1', 'order_id' => 'order_RZP0001', 'amount' => $amount, 'status' => 'captured',
        ]]]];

        $this->webhook($event(160000), 'bad-signature')->assertStatus(400);
        $this->webhook($event(999))->assertNoContent(); // amount mismatch: acknowledged, not applied
        $this->assertSame('pending', Order::sole()->payment_status);

        $this->webhook($event(160000))->assertNoContent();
        $this->assertSame('paid', Order::sole()->payment_status);
        $this->assertSame('confirmed', Order::sole()->status);
    }

    public function test_failed_payment_can_be_retried_on_same_gateway_order(): void
    {
        $order = $this->placeOnlineOrder()['data'];

        $this->webhook(['event' => 'payment.failed', 'payload' => ['payment' => ['entity' => [
            'id' => 'pay_FAIL', 'order_id' => 'order_RZP0001', 'amount' => 160000,
            'error_code' => 'BAD_REQUEST_ERROR', 'error_description' => 'Payment was declined by the bank.',
        ]]]])->assertNoContent();

        $this->assertSame('failed', Order::sole()->payment_status);

        $this->postJson("/api/v1/orders/{$order['order_number']}/pay", [], self::GUEST)
            ->assertOk()
            ->assertJsonPath('data.order_id', 'order_RZP0001');

        Http::assertSentCount(1); // reused, no second gateway order
    }

    public function test_customer_can_switch_unpaid_order_to_cod(): void
    {
        $order = $this->placeOnlineOrder()['data'];

        $this->postJson("/api/v1/orders/{$order['order_number']}/cod", [], self::GUEST)
            ->assertOk()
            ->assertJsonPath('data.payment_method', 'cod')
            ->assertJsonPath('data.can_pay', false);

        $this->postJson("/api/v1/orders/{$order['order_number']}/pay", [], self::GUEST)->assertStatus(422);
    }

    public function test_unpaid_orders_expire_and_release_stock(): void
    {
        $this->placeOnlineOrder();
        $this->assertSame(3, $this->product->fresh()->stock);

        $this->artisan('orders:expire-unpaid')->assertSuccessful();
        $this->assertSame('pending', Order::sole()->status); // still inside the window

        $this->travel(31)->minutes();
        $this->artisan('orders:expire-unpaid')->expectsOutputToContain('Expired 1')->assertSuccessful();

        $order = Order::sole();
        $this->assertSame('cancelled', $order->status);
        $this->assertSame('expired', $order->payment_status);
        $this->assertSame(5, $this->product->fresh()->stock);
    }

    public function test_late_payment_on_expired_order_is_flagged_for_refund(): void
    {
        $this->placeOnlineOrder();
        $this->travel(31)->minutes();
        $this->artisan('orders:expire-unpaid');

        $this->webhook(['event' => 'payment.captured', 'payload' => ['payment' => ['entity' => [
            'id' => 'pay_LATE', 'order_id' => 'order_RZP0001', 'amount' => 160000,
        ]]]])->assertNoContent();

        $order = Order::sole();
        $this->assertSame('cancelled', $order->status);
        $this->assertSame('paid', $order->payment_status);
        $this->assertSame(Payment::STATUS_PAID, Payment::sole()->status);
    }

    public function test_online_checkout_rejected_when_gateway_not_configured(): void
    {
        config(['services.razorpay.key_secret' => null]);
        $this->app->forgetInstance(RazorpayGateway::class);

        $this->postJson('/api/v1/cart/items', ['product_id' => $this->product->id, 'quantity' => 1], self::GUEST)->assertOk();
        $this->postJson('/api/v1/orders', [
            'email' => 'buyer@example.com', 'shipping_name' => 'A', 'shipping_phone' => '9876543210',
            'shipping_address_line' => '4 Lake View Road', 'shipping_city' => 'Chennai', 'shipping_state' => 'Tamil Nadu',
            'shipping_pincode' => '600020', 'payment_method' => 'razorpay',
        ], self::GUEST)->assertStatus(422)->assertJsonValidationErrors('payment_method');
    }
}
