<?php

use App\Models\Cart;
use App\Services\Payments\PaymentService;
use App\Support\Demo;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Schedule;
use Illuminate\Support\Facades\Storage;

Artisan::command('orders:expire-unpaid', function (PaymentService $payments) {
    $count = $payments->expireUnpaid();
    $this->info("Expired {$count} unpaid online order(s).");
})->purpose('Cancel online-payment orders left unpaid past the payment window and release their stock');

Artisan::command('carts:prune {--guest-days=14} {--empty-days=30}', function () {
    // Guest carts belong to a browser that may never come back; empty carts hold nothing.
    $guest = Cart::query()->whereNull('user_id')->where('updated_at', '<', now()->subDays((int) $this->option('guest-days')))->delete();
    $empty = Cart::query()->doesntHave('items')->where('updated_at', '<', now()->subDays((int) $this->option('empty-days')))->delete();
    $this->info("Pruned {$guest} stale guest cart(s) and {$empty} empty cart(s).");
})->purpose('Delete abandoned guest carts and long-empty carts');

Artisan::command('demo:reset {--force : Skip the confirmation (used by the scheduler)}', function () {
    // Hard safety stop: this wipes the database, so it only ever runs in the public demo.
    if (! Demo::enabled()) {
        $this->error('DEMO_MODE is off — refusing to reset. This command would delete a real store\'s data.');

        return 1;
    }

    if (! $this->option('force') && ! $this->confirm('Delete ALL data and rebuild the demo store?')) {
        return 0;
    }

    $this->call('migrate:fresh', ['--seed' => true, '--force' => true]);

    // Uploads are blocked in the demo, but clear the folders in case any slipped in before.
    foreach (['products', 'categories'] as $folder) {
        Storage::disk('public')->deleteDirectory($folder);
    }
    Cache::forget('seo:sitemap');

    $this->info('Demo store reset to fresh demo data.');

    return 0;
})->purpose('Public demo only: wipe the database and re-seed the demo store');

/*
| The scheduler needs one process running it: `php artisan schedule:work`
| (the Docker "scheduler" service), or a cron entry calling `schedule:run` every minute.
*/
Schedule::command('orders:expire-unpaid')->everyFiveMinutes()->withoutOverlapping();
Schedule::command('carts:prune')->dailyAt('03:00')->withoutOverlapping();
Schedule::command('sanctum:prune-expired --hours=48')->dailyAt('03:10');
Schedule::command('queue:prune-failed --hours=168')->weekly();

Schedule::command('demo:reset --force')
    ->dailyAt(config('shop.demo.reset_at', '03:30'))
    ->when(fn () => Demo::enabled())
    ->withoutOverlapping();
