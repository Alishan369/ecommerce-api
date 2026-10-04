<?php

namespace App\Providers;

use App\Models\User;
use App\Repositories\AddressRepository;
use App\Repositories\CartRepository;
use App\Repositories\CategoryRepository;
use App\Repositories\Interfaces\AddressRepositoryInterface;
use App\Repositories\Interfaces\CartRepositoryInterface;
use App\Repositories\Interfaces\CategoryRepositoryInterface;
use App\Repositories\Interfaces\OrderRepositoryInterface;
use App\Repositories\Interfaces\ProductRepositoryInterface;
use App\Repositories\OrderRepository;
use App\Repositories\ProductRepository;
use App\Services\Payments\RazorpayGateway;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Middleware\TrustProxies;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->bind(CategoryRepositoryInterface::class, CategoryRepository::class);
        $this->app->bind(ProductRepositoryInterface::class, ProductRepository::class);
        $this->app->bind(CartRepositoryInterface::class, CartRepository::class);
        $this->app->bind(OrderRepositoryInterface::class, OrderRepository::class);
        $this->app->bind(AddressRepositoryInterface::class, AddressRepository::class);

        $this->app->singleton(RazorpayGateway::class, fn () => RazorpayGateway::fromConfig());
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // Behind a CDN or load balancer (e.g. Cloudflare), trust its X-Forwarded-* headers so
        // request()->ip() — used by every rate limit — and isSecure() see the real visitor.
        if ($proxies = config('app.trusted_proxies')) {
            TrustProxies::at($proxies === '*' ? '*' : array_map('trim', explode(',', $proxies)));
        }

        if (config('app.force_https')) {
            URL::forceScheme('https');
        }

        // Reset links open the storefront's reset page, which posts back to /api/v1/auth/reset-password.
        ResetPassword::createUrlUsing(fn (User $user, string $token) => config('shop.frontend_url')
            .'/reset-password?token='.urlencode($token).'&email='.urlencode($user->email));

        // Used by the admin route group (`can:access-admin`). Authentication is not authorization.
        Gate::define('access-admin', fn (User $user) => $user->isAdmin());

        RateLimiter::for('public', fn (Request $r) => Limit::perMinute(120)->by($r->ip())
        );

        // Keyed by user or IP — never by the client-supplied cart header, which could be rotated freely.
        RateLimiter::for('cart', fn (Request $r) => Limit::perMinute(60)->by(
            $r->user('sanctum')?->id ?: $r->ip()
        )
        );

        RateLimiter::for('checkout', fn (Request $r) => Limit::perMinute(10)->by(
            $r->user('sanctum')?->id ?: $r->ip()
        )
        );

        RateLimiter::for('auth', fn (Request $r) => [
            Limit::perMinute(20)->by($r->ip()),
            Limit::perMinute(5)->by(strtolower((string) $r->input('email')).'|'.$r->ip()),
        ]);

        RateLimiter::for('order-lookup', fn (Request $r) => [
            Limit::perMinute(5)->by($r->ip()),
            Limit::perDay(30)->by($r->ip()),
        ]);

        RateLimiter::for('authenticated', fn (Request $r) => Limit::perMinute(90)->by($r->user()?->id ?: $r->ip())
        );

        // "Forgot password" sends an email — keep it from being used to spam an inbox.
        RateLimiter::for('password-reset', fn (Request $r) => [
            Limit::perMinute(5)->by($r->ip()),
            Limit::perHour(5)->by(strtolower((string) $r->input('email'))),
        ]);

        // Applying coupon codes: tight enough that guessing codes is impractical.
        RateLimiter::for('coupon', fn (Request $r) => [
            Limit::perMinute(10)->by($r->user('sanctum')?->id ?: $r->ip()),
            Limit::perDay(100)->by($r->ip()),
        ]);
    }
}
