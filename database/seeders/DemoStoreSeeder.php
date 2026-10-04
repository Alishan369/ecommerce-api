<?php

namespace Database\Seeders;

use App\Models\Address;
use App\Models\Order;
use App\Models\Payment;
use App\Models\Product;
use App\Models\User;
use App\Services\CouponService;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

/**
 * Demo customers, saved addresses and ~30 days of order history so the
 * storefront account pages and the admin dashboard have something to show.
 *
 * Only runs locally or in the public demo (DEMO_MODE) — see DatabaseSeeder.
 * Demo online payments use `demo_` gateway ids — they do not exist in any
 * Razorpay account. The first customer's login comes from config/shop.php →
 * demo.customer, so it always matches what the demo sign-in page shows.
 */
class DemoStoreSeeder extends Seeder
{
    /** Codes from CouponSeeder that demo shoppers "try" at checkout. */
    private const DEMO_CODES = ['WELCOME10', 'FLAT200', 'FESTIVE15', 'FREESHIP'];

    private const CUSTOMERS = [
        ['name' => 'Aarav Mehta', 'email' => null /* config: shop.demo.customer.email */, 'phone' => '9876543210', 'city' => 'Mumbai', 'state' => 'Maharashtra', 'pincode' => '400050', 'line' => '12, Sea Breeze Apartments, Hill Road, Bandra West'],
        ['name' => 'Priya Sharma', 'email' => 'priya.sharma@example.com', 'phone' => '9811122233', 'city' => 'New Delhi', 'state' => 'Delhi', 'pincode' => '110024', 'line' => 'B-42, Lajpat Nagar II'],
        ['name' => 'Rohan Iyer', 'email' => 'rohan.iyer@example.com', 'phone' => '9845098450', 'city' => 'Bengaluru', 'state' => 'Karnataka', 'pincode' => '560038', 'line' => '221, 12th Main, Indiranagar'],
        ['name' => 'Ananya Gupta', 'email' => 'ananya.gupta@example.com', 'phone' => '9930099300', 'city' => 'Pune', 'state' => 'Maharashtra', 'pincode' => '411001', 'line' => 'Flat 7, Koregaon Park Lane 5'],
        ['name' => 'Kabir Singh', 'email' => 'kabir.singh@example.com', 'phone' => '9888877766', 'city' => 'Jaipur', 'state' => 'Rajasthan', 'pincode' => '302001', 'line' => '18, MI Road, Near Panch Batti'],
        ['name' => 'Meera Nair', 'email' => 'meera.nair@example.com', 'phone' => '9447012345', 'city' => 'Kochi', 'state' => 'Kerala', 'pincode' => '682016', 'line' => '3rd Floor, Marine Drive Residency'],
    ];

    /** Older orders are further along the fulfilment pipeline. */
    private function statusForAge(int $daysAgo): string
    {
        return match (true) {
            $daysAgo >= 8 => mt_rand(1, 10) === 1 ? 'cancelled' : 'delivered',
            $daysAgo >= 4 => ['shipped', 'delivered', 'delivered'][mt_rand(0, 2)],
            $daysAgo >= 2 => ['processing', 'shipped'][mt_rand(0, 1)],
            default => ['confirmed', 'processing', 'pending'][mt_rand(0, 2)],
        };
    }

    public function run(): void
    {
        mt_srand(2026); // same demo data on every run

        $products = Product::query()->where('is_active', true)->where('stock', '>', 0)->get();

        if ($products->isEmpty()) {
            $this->command?->warn('No products found — run CatalogSeeder first.');

            return;
        }

        $login = config('shop.demo.customer');

        $customers = collect(self::CUSTOMERS)->map(function (array $c, int $i) use ($login) {
            $c['email'] ??= strtolower($login['email']);

            $user = User::query()->updateOrCreate(
                ['email' => $c['email']],
                [
                    'name' => $c['name'],
                    'phone' => $c['phone'],
                    'password' => $login['password'],
                    'role' => 'customer',
                    'email_verified_at' => now(),
                ],
            );
            $user->forceFill(['created_at' => now()->subDays(60 - $i * 7)])->save();

            Address::query()->updateOrCreate(
                ['user_id' => $user->id, 'pincode' => $c['pincode']],
                [
                    'name' => $c['name'],
                    'phone' => $c['phone'],
                    'address_line' => $c['line'],
                    'city' => $c['city'],
                    'state' => $c['state'],
                    'is_default' => true,
                ],
            );

            return $user->setRelation('demoAddress', $c);
        });

        // Re-running replaces earlier demo orders instead of piling up duplicates.
        Order::query()->whereIn('user_id', $customers->pluck('id'))->where('notes', 'Demo order')->delete();

        $created = 0;
        for ($daysAgo = 29; $daysAgo >= 0; $daysAgo--) {
            $ordersToday = mt_rand(0, 100) < 70 ? mt_rand(1, 3) : 0;

            for ($n = 0; $n < $ordersToday; $n++) {
                $customer = $customers->random();
                $placedAt = now()->subDays($daysAgo)->setTime(mt_rand(9, 22), mt_rand(0, 59));
                $this->createOrder($customer, $customer->getRelation('demoAddress'), $products, $placedAt, $this->statusForAge($daysAgo));
                $created++;
            }
        }

        $this->command?->info("Demo store ready: {$customers->count()} customers, {$created} orders. Customer login: "
            .$login['email'].' / '.$login['password']);
    }

    private function createOrder(User $user, array $address, Collection $products, Carbon $placedAt, string $status): void
    {
        $lines = $products->random(mt_rand(1, 3))->map(fn (Product $p) => [
            'product' => $p,
            'quantity' => mt_rand(1, 10) > 8 ? 2 : 1,
            'unit_price' => (float) ($p->sale_price ?? $p->price),
        ]);

        $subtotal = round($lines->sum(fn ($l) => $l['unit_price'] * $l['quantity']), 2);

        // About a third of shoppers try a coupon; the real rules decide whether it applies
        // (first order, per-customer limit, minimum order), exactly as at checkout.
        $coupons = app(CouponService::class);
        $coupon = mt_rand(1, 100) <= 35 ? $coupons->findByCode(self::DEMO_CODES[mt_rand(0, count(self::DEMO_CODES) - 1)]) : null;
        $totals = $coupons->totals($subtotal, $coupon, $user);
        if ($coupon && $totals['coupon_error']) {
            $coupon = null;
            $totals = $coupons->totals($subtotal, null, $user);
        }
        $online = mt_rand(1, 10) <= 6; // ~60% prepaid, like most Indian D2C stores

        // A successful online payment confirms the order (PaymentService::markPaid does the same).
        if ($online && $status === 'pending') {
            $status = 'confirmed';
        }

        // Same rules as the live app: prepaid orders are paid unless they expired unpaid;
        // COD becomes paid when the order is delivered (cash collected).
        $paymentStatus = $online
            ? ($status === 'cancelled' ? 'expired' : 'paid')
            : ($status === 'delivered' ? 'paid' : 'pending');

        $order = Order::create([
            'user_id' => $user->id,
            'order_number' => 'ORD-'.$placedAt->format('Ymd').'-'.strtoupper(Str::random(8)),
            'email' => $user->email,
            'shipping_name' => $address['name'],
            'shipping_phone' => $address['phone'],
            'shipping_address_line' => $address['line'],
            'shipping_city' => $address['city'],
            'shipping_state' => $address['state'],
            'shipping_pincode' => $address['pincode'],
            'subtotal' => $subtotal,
            'shipping_amount' => $totals['shipping'],
            'discount_amount' => $totals['discount'],
            'coupon_id' => $coupon?->id,
            'coupon_code' => $coupon?->code,
            'total' => $totals['total'],
            'status' => $status,
            'payment_method' => $online ? 'razorpay' : 'cod',
            'payment_status' => $paymentStatus,
            'notes' => 'Demo order',
            'placed_at' => $placedAt,
        ]);
        $order->forceFill(['created_at' => $placedAt, 'updated_at' => $placedAt])->save();

        foreach ($lines as $line) {
            $order->items()->create([
                'product_id' => $line['product']->id,
                'product_name' => $line['product']->name,
                'product_sku' => $line['product']->sku,
                'unit_price' => $line['unit_price'],
                'quantity' => $line['quantity'],
                'subtotal' => round($line['unit_price'] * $line['quantity'], 2),
            ]);
        }

        if ($online && $paymentStatus === 'paid') {
            $order->payments()->create([
                'gateway' => 'razorpay',
                'gateway_order_id' => 'demo_order_'.Str::lower(Str::random(14)),
                'gateway_payment_id' => 'demo_pay_'.Str::lower(Str::random(14)),
                'amount' => $order->amountInPaise(),
                'currency' => 'INR',
                'status' => Payment::STATUS_PAID,
                'paid_at' => $placedAt->copy()->addMinutes(2),
            ]);
        }
    }
}
