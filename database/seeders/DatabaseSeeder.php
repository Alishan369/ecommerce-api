<?php

namespace Database\Seeders;

use App\Support\Demo;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * `php artisan migrate:fresh --seed`
     *
     * A real production store gets the admin account and the catalogue only.
     * Local machines and the public demo (DEMO_MODE=true) also get demo coupons,
     * customers and 30 days of order history (CouponSeeder, DemoStoreSeeder).
     */
    public function run(): void
    {
        $this->call([
            AdminUserSeeder::class,
            CatalogSeeder::class,
        ]);

        if (! app()->isProduction() || Demo::enabled()) {
            $this->call([CouponSeeder::class, DemoStoreSeeder::class]);
        }
    }
}
