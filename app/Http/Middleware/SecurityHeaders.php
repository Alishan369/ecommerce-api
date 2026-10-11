<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Baseline security headers for every response from Laravel (API, sitemap,
 * health). The storefront's static files get the same set from its host
 * (frontend/vercel.json), so both halves of the site behave alike.
 */
class SecurityHeaders
{
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);
        $headers = $response->headers;

        $headers->set('X-Content-Type-Options', 'nosniff');
        $headers->set('X-Frame-Options', 'DENY');
        $headers->set('Referrer-Policy', 'strict-origin-when-cross-origin');
        $headers->set('Permissions-Policy', 'camera=(), microphone=(), geolocation=(), payment=(self "https://checkout.razorpay.com")');
        $headers->set('Cross-Origin-Opener-Policy', 'same-origin-allow-popups'); // Razorpay Checkout opens popups/redirects
        // PHP adds this itself (expose_php) — don't advertise the version. The Docker image also sets expose_php=Off.
        $headers->remove('X-Powered-By');
        if (function_exists('header_remove') && ! headers_sent()) {
            header_remove('X-Powered-By');
        }

        if ($request->is('api/*')) {
            // JSON only: nothing in an API response may load resources or be framed.
            $headers->set('Content-Security-Policy', "default-src 'none'; frame-ancestors 'none'");

            // Signed-in responses carry personal data (orders, addresses) — keep them out of browser and proxy caches.
            if ($request->bearerToken()) {
                $headers->set('Cache-Control', 'no-store, private');
            }
        }

        // Only over HTTPS in production — sending HSTS on http://localhost would pin dev browsers to https.
        if ($request->isSecure() && app()->isProduction()) {
            $headers->set('Strict-Transport-Security', 'max-age=31536000; includeSubDomains');
        }

        return $response;
    }
}
