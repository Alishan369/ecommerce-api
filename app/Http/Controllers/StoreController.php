<?php

namespace App\Http\Controllers;

use App\Services\Payments\RazorpayGateway;
use App\Support\Demo;
use App\Support\Shipping;
use Illuminate\Http\JsonResponse;

/** GET /api/v1/store — public storefront settings the SPA needs at startup. */
class StoreController extends Controller
{
    public function __invoke(RazorpayGateway $razorpay): JsonResponse
    {
        $testPayments = str_starts_with((string) $razorpay->keyId(), 'rzp_test_');

        return response()->json(['data' => [
            'name' => config('app.name'),
            'currency' => 'INR',
            'free_shipping_threshold' => Shipping::threshold(),
            'shipping_fee' => (float) config('shop.shipping_fee'),
            'demo' => Demo::enabled() ? [
                'enabled' => true,
                // Shared logins are meant to be public in demo mode — they're printed on the sign-in page.
                'accounts' => array_map(fn ($a) => array_intersect_key($a, array_flip(['role', 'label', 'email', 'password'])), Demo::accounts()),
                // Only offered while Razorpay runs on test keys, so a demo can never take real money.
                'test_payments' => $testPayments ? [
                    'upi' => 'success@razorpay',
                    'card' => '4100 2800 0000 1007',
                    'card_note' => 'Any future expiry, any CVV, any 4–10 digit OTP',
                ] : null,
            ] : ['enabled' => false],
        ]]);
    }
}
