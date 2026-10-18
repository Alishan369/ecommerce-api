<?php

use App\Http\Controllers\Admin\BannerController as AdminBannerController;
use App\Http\Controllers\Admin\CouponController as AdminCouponController;
use App\Http\Controllers\Admin\CustomerController as AdminCustomerController;
use App\Http\Controllers\Admin\DashboardController as AdminDashboardController;
use App\Http\Controllers\Admin\ReportController as AdminReportController;
use App\Http\Controllers\Admin\ScentFamilyController as AdminScentFamilyController;
use App\Http\Controllers\Admin\SiteMediaController as AdminSiteMediaController;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\BannerController;
use App\Http\Controllers\CartController;
use App\Http\Controllers\CategoryController;
use App\Http\Controllers\CouponController;
use App\Http\Controllers\Customer\AddressController;
use App\Http\Controllers\HealthController;
use App\Http\Controllers\OrderController;
use App\Http\Controllers\PasswordResetController;
use App\Http\Controllers\PaymentController;
use App\Http\Controllers\ProductController;
use App\Http\Controllers\ScentFamilyController;
use App\Http\Controllers\SiteMediaController;
use App\Http\Controllers\StoreController;
use App\Models\Order;
use Illuminate\Support\Facades\Route;

Route::prefix('v1')->name('v1.')->group(function () {

    // Uptime monitors: database, cache and storage.
    Route::get('health', HealthController::class)->middleware('throttle:public')->name('health');

    /* ---------------------------------------------------------------
     | Auth — Sanctum personal access tokens (Bearer)
     * ------------------------------------------------------------- */
    Route::prefix('auth')->name('auth.')->group(function () {
        Route::middleware('throttle:auth')->group(function () {
            Route::post('register', [AuthController::class, 'register'])->name('register');
            Route::post('login', [AuthController::class, 'login'])->name('login');
        });

        // Forgot / reset password.
        Route::middleware('throttle:password-reset')->group(function () {
            Route::post('forgot-password', [PasswordResetController::class, 'sendLink'])->name('password.email');
            Route::post('reset-password', [PasswordResetController::class, 'reset'])->name('password.reset');
        });

        Route::middleware(['auth:sanctum', 'throttle:authenticated'])->group(function () {
            Route::get('user', [AuthController::class, 'user'])->name('user');
            Route::put('user', [AuthController::class, 'updateProfile'])->name('user.update');
            Route::put('password', [AuthController::class, 'updatePassword'])
                ->middleware('throttle:auth')
                ->name('password.update');
            Route::post('logout', [AuthController::class, 'logout'])->name('logout');
        });
    });

    /* ---------------------------------------------------------------
     | Public catalogue (read-only, cacheable)
     * ------------------------------------------------------------- */
    // Shared caches (browser, Vercel, CDN) may keep these for a minute and revalidate with the ETag,
    // so most storefront page loads never reach PHP or the database. Signed-in requests stay no-store.
    Route::middleware(['throttle:public', 'cache.headers:public;max_age=60;etag'])->group(function () {

        // Storefront settings (shipping) the SPA loads at startup.
        Route::get('store', StoreController::class)->name('store');

        Route::get('categories/tree', [CategoryController::class, 'tree'])->name('categories.tree');
        Route::get('categories/{category:slug}', [CategoryController::class, 'show'])->name('categories.show');

        Route::apiResource('products', ProductController::class)
            ->parameters(['products' => 'product:slug'])
            ->only(['index', 'show']);

        Route::get('payments/methods', [PaymentController::class, 'methods'])->name('payments.methods');

        // "Available offers" — live, public coupons.
        Route::get('coupons', [CouponController::class, 'index'])->name('coupons.index');

        // Home page banners (hero slider, promo row) — live ones only.
        Route::get('banners', [BannerController::class, 'index'])->name('banners.index');

        // Admin-replaced photos/videos for fixed storefront spots.
        Route::get('media', SiteMediaController::class)->name('media.index');

        // Scent families: names, colours, photos, order, product counts.
        Route::get('scent-families', ScentFamilyController::class)->name('scent-families.index');
    });

    /* ---------------------------------------------------------------
     | Cart — guests can build a cart (X-Cart-Session-Id); it is merged
     | into the customer's cart when they sign in.
     * ------------------------------------------------------------- */
    Route::prefix('cart')->name('cart.')->middleware('throttle:cart')->group(function () {
        Route::get('/', [CartController::class, 'show'])->name('show');

        Route::post('items', [CartController::class, 'addItem'])->name('items.add');

        // Stricter limit on applying codes so they can't be brute-forced.
        Route::post('coupon', [CartController::class, 'applyCoupon'])->middleware('throttle:coupon')->name('coupon.apply');
        Route::delete('coupon', [CartController::class, 'removeCoupon'])->name('coupon.remove');

        // {item} is the product id — a cart holds at most one line per product.
        Route::match(['put', 'patch'], 'items/{item}', [CartController::class, 'updateItem'])
            ->whereNumber('item')
            ->name('items.update');
        Route::delete('items/{item}', [CartController::class, 'removeItem'])
            ->whereNumber('item')
            ->name('items.remove');
    });

    /* ---------------------------------------------------------------
     | "Track your order" without signing in — order number + email.
     | POST so the email never lands in logs / Referer / browser history.
     * ------------------------------------------------------------- */
    Route::post('orders/lookup', [OrderController::class, 'lookup'])
        ->middleware('throttle:order-lookup')
        ->name('orders.lookup');

    /* ---------------------------------------------------------------
     | Razorpay webhook — authenticated by its HMAC signature, not a token.
     * ------------------------------------------------------------- */
    Route::post('payments/razorpay/webhook', [PaymentController::class, 'webhook'])->name('payments.razorpay.webhook');

    /* ---------------------------------------------------------------
     | Signed-in customers: checkout, orders, payments, address book.
     | Login is required before an order can be placed.
     * ------------------------------------------------------------- */
    Route::middleware('auth:sanctum')->group(function () {

        Route::middleware('throttle:checkout')->group(function () {
            Route::post('orders', [OrderController::class, 'store'])->name('orders.store');

            Route::post('orders/{orderNumber}/pay', [PaymentController::class, 'initiate'])
                ->where('orderNumber', Order::NUMBER_PATTERN)
                ->name('orders.pay');
            Route::post('orders/{orderNumber}/cod', [PaymentController::class, 'switchToCod'])
                ->where('orderNumber', Order::NUMBER_PATTERN)
                ->name('orders.cod');
            Route::post('orders/{orderNumber}/cancel', [OrderController::class, 'cancel'])
                ->where('orderNumber', Order::NUMBER_PATTERN)
                ->name('orders.cancel');
            Route::post('payments/razorpay/verify', [PaymentController::class, 'verify'])->name('payments.razorpay.verify');
        });

        Route::middleware('throttle:authenticated')->group(function () {
            Route::get('orders', [OrderController::class, 'index'])->name('orders.index');
            Route::get('orders/{orderNumber}', [OrderController::class, 'show'])
                ->where('orderNumber', Order::NUMBER_PATTERN)
                ->name('orders.show');

            Route::apiResource('addresses', AddressController::class)
                ->only(['index', 'store', 'update', 'destroy'])
                ->whereNumber('address');
        });
    });

    /* ---------------------------------------------------------------
     | Admin — authentication is NOT authorization.
     * ------------------------------------------------------------- */
    Route::prefix('admin')
        ->name('admin.')
        ->middleware(['auth:sanctum', 'can:access-admin', 'throttle:authenticated'])
        ->group(function () {

            Route::get('dashboard', AdminDashboardController::class)->name('dashboard');

            Route::get('categories/tree', [CategoryController::class, 'adminTree'])->name('categories.tree');
            Route::post('categories', [CategoryController::class, 'store'])->name('categories.store');
            Route::match(['put', 'patch'], 'categories/{category:slug}', [CategoryController::class, 'update'])->name('categories.update');
            Route::delete('categories/{category:slug}', [CategoryController::class, 'destroy'])->name('categories.destroy');

            // Admin binds products by id, not slug.
            Route::get('products', [ProductController::class, 'adminIndex'])->name('products.index');
            Route::post('products', [ProductController::class, 'store'])->name('products.store');
            Route::match(['put', 'patch'], 'products/{product:id}', [ProductController::class, 'update'])->name('products.update');
            Route::delete('products/{product:id}', [ProductController::class, 'destroy'])->name('products.destroy');

            Route::get('orders', [OrderController::class, 'adminIndex'])->name('orders.index');
            Route::get('orders/{order}', [OrderController::class, 'adminShow'])
                ->whereNumber('order')
                ->name('orders.show');
            Route::match(['put', 'patch'], 'orders/{order}', [OrderController::class, 'update'])
                ->whereNumber('order')
                ->name('orders.update');

            Route::get('customers', [AdminCustomerController::class, 'index'])->name('customers.index');

            Route::get('coupons', [AdminCouponController::class, 'index'])->name('coupons.index');
            Route::post('coupons', [AdminCouponController::class, 'store'])->name('coupons.store');
            Route::match(['put', 'patch'], 'coupons/{coupon}', [AdminCouponController::class, 'update'])
                ->whereNumber('coupon')
                ->name('coupons.update');
            Route::delete('coupons/{coupon}', [AdminCouponController::class, 'destroy'])
                ->whereNumber('coupon')
                ->name('coupons.destroy');

            Route::get('banners', [AdminBannerController::class, 'index'])->name('banners.index');
            Route::post('banners', [AdminBannerController::class, 'store'])->name('banners.store');
            Route::match(['put', 'patch'], 'banners/{banner}', [AdminBannerController::class, 'update'])
                ->whereNumber('banner')
                ->name('banners.update');
            Route::delete('banners/{banner}', [AdminBannerController::class, 'destroy'])
                ->whereNumber('banner')
                ->name('banners.destroy');

            Route::get('media', [AdminSiteMediaController::class, 'index'])->name('media.index');
            Route::post('media/{slot}', [AdminSiteMediaController::class, 'update'])
                ->where('slot', '[a-z0-9.-]+')
                ->name('media.update');
            Route::delete('media/{slot}', [AdminSiteMediaController::class, 'destroy'])
                ->where('slot', '[a-z0-9.-]+')
                ->name('media.destroy');

            Route::get('scent-families', [AdminScentFamilyController::class, 'index'])->name('scent-families.index');
            // Registered before {key} so "reorder" isn't read as a family key.
            Route::post('scent-families/reorder', [AdminScentFamilyController::class, 'reorder'])->name('scent-families.reorder');
            Route::match(['post', 'patch'], 'scent-families/{key}', [AdminScentFamilyController::class, 'update'])
                ->where('key', '[a-z]+')
                ->name('scent-families.update');

            // Reports: preview (JSON) and full CSV download for a date range.
            Route::get('reports/{type}', [AdminReportController::class, 'show'])
                ->where('type', '[a-z-]+')
                ->name('reports.show');
            Route::get('reports/{type}/download', [AdminReportController::class, 'download'])
                ->where('type', '[a-z-]+')
                ->middleware('throttle:10,1')
                ->name('reports.download');
        });
});

Route::fallback(fn () => response()->json([
    'message' => 'Endpoint not found.',
], 404));
