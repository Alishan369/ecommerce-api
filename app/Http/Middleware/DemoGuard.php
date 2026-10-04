<?php

namespace App\Http\Middleware;

use App\Support\Demo;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpFoundation\Response;

/**
 * Public demo protection (DEMO_MODE=true). Visitors share the demo accounts,
 * so anything that would break the demo for the next person is refused with a
 * friendly 422 — everything else (orders, status changes, edits) still works
 * and the data resets nightly (demo:reset).
 *
 * Usage on routes:
 *   demo.guard           — always blocked in demo mode (e.g. deletes)
 *   demo.guard:uploads   — blocked only when a file is uploaded
 *   demo.guard:accounts  — blocked when it targets a shared demo account (password, profile)
 */
class DemoGuard
{
    public const MESSAGE = 'This action is turned off in the public demo (the store resets every night). Everything works normally on a real store.';

    public function handle(Request $request, Closure $next, string $mode = 'always'): Response
    {
        if (! Demo::enabled()) {
            return $next($request);
        }

        $blocked = match ($mode) {
            'uploads' => $request->hasFile('image'),
            'accounts' => Demo::isDemoAccount($request->user('sanctum')?->email ?? $request->input('email')),
            default => true,
        };

        if ($blocked) {
            throw ValidationException::withMessages(['demo' => self::MESSAGE]);
        }

        return $next($request);
    }
}
