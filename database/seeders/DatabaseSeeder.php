<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * `php artisan migrate:fresh --seed`
     *
     * Creates the admin account, then loads the catalogue saved by
     * `php artisan catalog:export` (if any) into an empty store.
     */
    public function run(): void
    {
        $this->call([AdminUserSeeder::class, CatalogSeeder::class]);
    }
}
