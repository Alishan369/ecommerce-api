<?php

namespace Database\Seeders;

use App\Models\User;
use App\Support\Demo;
use Illuminate\Database\Seeder;
use RuntimeException;

/**
 * Creates (or resets) the store admin from config/shop.php → ADMIN_* env vars.
 * Safe to re-run: `php artisan db:seed --class=AdminUserSeeder`.
 */
class AdminUserSeeder extends Seeder
{
    public function run(): void
    {
        $admin = config('shop.admin');

        // The default password is printed in the README. A real (non-demo) production
        // store must set its own — in the public demo it's shown on the sign-in page anyway.
        if (app()->isProduction() && ! Demo::enabled()) {
            if ($admin['password'] === config('shop.default_admin_password') || strlen((string) $admin['password']) < 12) {
                throw new RuntimeException('Set a strong ADMIN_PASSWORD (12+ characters, not the README default) in .env before seeding production.');
            }
        }

        User::query()->updateOrCreate(
            ['email' => strtolower($admin['email'])],
            [
                'name' => $admin['name'],
                'password' => $admin['password'], // hashed by the model cast
                'role' => 'admin',
                'email_verified_at' => now(),
            ],
        );

        $this->command?->info("Admin ready: {$admin['email']}");
    }
}
