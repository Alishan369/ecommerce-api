<?php

namespace App\Http\Controllers;

use App\Support\Shipping;
use Illuminate\Http\JsonResponse;

/** GET /api/v1/store — public storefront settings the SPA needs at startup. */
class StoreController extends Controller
{
    public function __invoke(): JsonResponse
    {
        return response()->json(['data' => [
            'name' => config('app.name'),
            'currency' => 'INR',
            'free_shipping_threshold' => Shipping::threshold(),
            'shipping_fee' => (float) config('shop.shipping_fee'),
        ]]);
    }
}
