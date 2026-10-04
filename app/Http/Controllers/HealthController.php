<?php

namespace App\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Throwable;

/**
 * GET /api/v1/health — for uptime monitors and the Docker healthcheck.
 * Booleans only (no versions, paths or error text), 503 if anything is down.
 */
class HealthController extends Controller
{
    public function __invoke(): JsonResponse
    {
        $checks = [
            'database' => $this->attempt(fn () => DB::select('select 1')),
            'cache' => $this->attempt(function () {
                Cache::put('health:ping', 1, 10);

                return Cache::get('health:ping') === 1;
            }),
            'storage' => is_writable(storage_path('framework')) && is_writable(storage_path('app/public')),
        ];

        $ok = ! in_array(false, $checks, true);

        return response()->json([
            'status' => $ok ? 'ok' : 'degraded',
            'checks' => $checks,
        ], $ok ? 200 : 503);
    }

    private function attempt(callable $check): bool
    {
        try {
            return $check() !== false;
        } catch (Throwable) {
            return false;
        }
    }
}
