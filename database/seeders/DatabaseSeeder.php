<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * `php artisan migrate:fresh --seed`
     *
     * Creates only the admin account. Collections, products, banners, photos
     * and coupons are all added from the admin panel.
     */
    public function run(): void
    {
        $this->call(AdminUserSeeder::class);
    }
}
