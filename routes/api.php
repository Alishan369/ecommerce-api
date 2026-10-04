<?php

use App\Http\Controllers\Admin\CouponController as AdminCouponController;
use App\Http\Controllers\Admin\CustomerController as AdminCustomerController;
use App\Http\Controllers\Admin\DashboardController as AdminDashboardController;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\CartController;
use App\Http\Controllers\CategoryController;
use App\Http\Controllers\CouponController;
use App\Http\Controllers\Customer\AddressController;
use App\Http\Controllers\HealthController;
use App\Http\Controllers\OrderController;
use App\Http\Controllers\PasswordResetController;
use App\Http\Controllers\PaymentController;
use App\Http\Controllers\ProductController;
use App\Http\Controllers\StoreController;
use Illuminate\Support\Facades\Route;

$orderNumberPattern = 'ORD-[0-9]{8}-[A-Z0-9]{8}';

Route::get('/', fn () => response()->json([
    'status' => 'success',
    'message' => 'API is working!',
    'version' => 'v1',
]))->name('health');

Route::prefix('v1')->name('v1.')->group(function () use ($orderNumberPattern) {

    // Uptime monitors / Docker healthcheck: database, cache and storage.
    Route::get('health', HealthController::class)->middleware('throttle:public')->name('health');

    /* ---------------------------------------------------------------
     | Auth — Sanctum personal access tokens (Bearer)
     * ------------------------------------------------------------- */
    Route::prefix('auth')->name('auth.')->group(function () {
        Route::middleware('throttle:auth')->group(function () {
            Route::post('register', [AuthController::class, 'register'])->name('register');
            Route::post('login', [AuthController::class, 'login'])->name('login');
        });

        // Forgot / reset password. Shared demo accounts can't be reset in the public demo.
        Route::middleware(['throttle:password-reset', 'demo.guard:accounts'])->group(function () {
            Route::post('forgot-password', [PasswordResetController::class, 'sendLink'])->name('password.email');
            Route::post('reset-password', [PasswordResetController::class, 'reset'])->name('password.reset');
        });

        Route::middleware(['auth:sanctum', 'throttle:authenticated'])->group(function () {
            Route::get('user', [AuthController::class, 'user'])->name('user');
            Route::put('user', [AuthController::class, 'updateProfile'])
                ->middleware('demo.guard:accounts')
                ->name('user.update');
            Route::put('password', [AuthController::class, 'updatePassword'])
                ->middleware(['throttle:auth', 'demo.guard:accounts'])
                ->name('password.update');
            Route::post('logout', [AuthController::class, 'logout'])->name('logout');
        });
    });

    /* ---------------------------------------------------------------
     | Public catalogue (read-only, cacheable)
     * ------------------------------------------------------------- */
    Route::middleware('throttle:public')->group(function () {

        // Storefront settings (shipping, demo mode) the SPA loads at startup.
        Route::get('store', StoreController::class)->name('store');

        Route::get('categories/tree', [CategoryController::class, 'tree'])->name('categories.tree');

        Route::apiResource('categories', CategoryController::class)
            ->parameters(['categories' => 'category:slug'])
            ->only(['index', 'show']);

        Route::apiResource('products', ProductController::class)
            ->parameters(['products' => 'product:slug'])
            ->only(['index', 'show']);

        Route::get('payments/methods', [PaymentController::class, 'methods'])->name('payments.methods');

        // "Available offers" — live, public coupons.
        Route::get('coupons', [CouponController::class, 'index'])->name('coupons.index');
    });

    /* ---------------------------------------------------------------
     | Cart — guests can build a cart (X-Cart-Session-Id); it is merged
     | into the customer's cart when they sign in.
     * ------------------------------------------------------------- */
    Route::prefix('cart')->name('cart.')->middleware('throttle:cart')->group(function () {
        Route::get('/', [CartController::class, 'show'])->name('show');
        Route::delete('/', [CartController::class, 'clear'])->name('clear');

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
    Route::post('payments/razorpay/webhook', [PaymentController::class, 'webhook'])
        ->name('payments.razorpay.webhook');

    /* ---------------------------------------------------------------
     | Signed-in customers: checkout, orders, payments, address book.
     | Login is required before an order can be placed.
     * ------------------------------------------------------------- */
    Route::middleware('auth:sanctum')->group(function () use ($orderNumberPattern) {

        Route::middleware('throttle:checkout')->group(function () use ($orderNumberPattern) {
            Route::post('orders', [OrderController::class, 'store'])->name('orders.store');

            Route::post('orders/{orderNumber}/pay', [PaymentController::class, 'initiate'])
                ->where('orderNumber', $orderNumberPattern)
                ->name('orders.pay');
            Route::post('orders/{orderNumber}/cod', [PaymentController::class, 'switchToCod'])
                ->where('orderNumber', $orderNumberPattern)
                ->name('orders.cod');
            Route::post('orders/{orderNumber}/cancel', [OrderController::class, 'cancel'])
                ->where('orderNumber', $orderNumberPattern)
                ->name('orders.cancel');
            Route::post('payments/razorpay/verify', [PaymentController::class, 'verify'])
                ->name('payments.razorpay.verify');
        });

        Route::middleware('throttle:authenticated')->group(function () use ($orderNumberPattern) {
            Route::get('orders', [OrderController::class, 'index'])->name('orders.index');
            Route::get('orders/{orderNumber}', [OrderController::class, 'show'])
                ->where('orderNumber', $orderNumberPattern)
                ->name('orders.show');

            Route::apiResource('addresses', AddressController::class)
                ->only(['index', 'store', 'update', 'destroy'])
                ->whereNumber('address');
        });
    });

    /* ---------------------------------------------------------------
     | Admin — authentication is NOT authorization.
     | In the public demo: no deletes and no file uploads (demo.guard).
     * ------------------------------------------------------------- */
    Route::prefix('admin')
        ->name('admin.')
        ->middleware(['auth:sanctum', 'can:access-admin', 'throttle:authenticated'])
        ->group(function () {

            Route::get('dashboard', AdminDashboardController::class)->name('dashboard');

            Route::get('categories/tree', [CategoryController::class, 'adminTree'])->name('categories.tree');
            Route::post('categories', [CategoryController::class, 'store'])
                ->middleware('demo.guard:uploads')
                ->name('categories.store');
            Route::match(['put', 'patch'], 'categories/{category:slug}', [CategoryController::class, 'update'])
                ->middleware('demo.guard:uploads')
                ->name('categories.update');
            Route::delete('categories/{category:slug}', [CategoryController::class, 'destroy'])
                ->middleware('demo.guard')
                ->name('categories.destroy');

            // Admin binds products by id, not slug.
            Route::get('products', [ProductController::class, 'adminIndex'])->name('products.index');
            Route::post('products', [ProductController::class, 'store'])
                ->middleware('demo.guard:uploads')
                ->name('products.store');
            Route::match(['put', 'patch'], 'products/{product:id}', [ProductController::class, 'update'])
                ->middleware('demo.guard:uploads')
                ->name('products.update');
            Route::delete('products/{product:id}', [ProductController::class, 'destroy'])
                ->middleware('demo.guard')
                ->name('products.destroy');

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
                ->middleware('demo.guard')
                ->name('coupons.destroy');
        });
});

Route::fallback(fn () => response()->json([
    'message' => 'Endpoint not found.',
], 404));
