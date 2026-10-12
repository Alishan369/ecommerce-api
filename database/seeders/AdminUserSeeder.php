<?php

namespace Database\Seeders;

use App\Models\User;
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

        // An empty ADMIN_EMAIL= would otherwise create an admin who can't receive a reset link.
        if (! filter_var($admin['email'], FILTER_VALIDATE_EMAIL)) {
            throw new RuntimeException('Set ADMIN_EMAIL to a real email address in .env before seeding.');
        }

        // The default password is printed in the README, so production must set its own.
        if (app()->isProduction()) {
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
