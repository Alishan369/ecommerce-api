<?php

use App\Http\Controllers\SeoController;
use App\Mail\OrderPlaced;
use App\Mail\OrderStatusUpdated;
use App\Models\Order;
use App\Models\User;
use App\Notifications\ResetPasswordLink;
use Illuminate\Support\Facades\Route;

/*
| The storefront is a separate React app. Laravel only answers the API
| (routes/api.php) plus these few public URLs, which the web server forwards.
*/

// Visiting the API host directly: say what this is instead of a framework splash page.
Route::get('/', fn () => response()->json([
    'service' => config('app.name').' API',
    'storefront' => config('shop.frontend_url'),
    'api' => url('/api/v1'),
    'health' => url('/api/v1/health'),
]));

Route::get('/robots.txt', [SeoController::class, 'robots']);
Route::get('/sitemap.xml', [SeoController::class, 'sitemap']);

// Local only: preview the customer emails in a browser, e.g. /dev/mail/placed or /dev/mail/shipped.
if (app()->environment('local')) {
    Route::get('/dev/mail/{type}', function (string $type) {
        $order = Order::query()->with('items')->latest('id')->firstOrFail();

        return match ($type) {
            'placed' => new OrderPlaced($order),
            'shipped', 'delivered', 'cancelled' => new OrderStatusUpdated($order->setAttribute('status', $type)),
            'reset' => (new ResetPasswordLink('preview-token'))->toMail($order->user ?? User::query()->firstOrFail())->render(),
            default => abort(404),
        };
    })->whereIn('type', ['placed', 'shipped', 'delivered', 'cancelled', 'reset']);
}
