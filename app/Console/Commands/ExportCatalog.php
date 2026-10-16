<?php

namespace App\Console\Commands;

use Database\Seeders\CatalogSeeder;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Storage;

/**
 * Saves the catalogue — collections, products, scent families, banners, site media and coupons —
 * plus the photos and videos they use into database/seeders/catalog. CatalogSeeder loads it into
 * a fresh database. Commit the folder to publish it.
 * Orders, customers, carts and passwords are never exported.
 *
 *   php artisan catalog:export
 */
class ExportCatalog extends Command
{
    protected $signature = 'catalog:export';

    protected $description = 'Save the catalogue and its images into database/seeders/catalog for fresh installs';

    public function handle(): int
    {
        $disk = Storage::disk('public');
        $data = [];
        $files = [];

        foreach (CatalogSeeder::TABLES as $table => $mediaColumns) {
            $rows = DB::table($table)->orderBy('id')->get()->map(fn ($row) => (array) $row)->all();

            foreach ($rows as &$row) {
                foreach ($mediaColumns as $column) {
                    $path = $row[$column] ?? null;
                    if (! CatalogSeeder::isLocalFile($path)) {
                        continue;
                    }

                    if ($disk->exists($path)) {
                        $files[$path] = true;
                    } else {
                        // The storefront shows its placeholder instead of a broken image.
                        $this->warn("{$table} #{$row['id']}: {$column} file is missing ({$path}) — exported without it.");
                        $row[$column] = null;
                    }
                }
            }
            unset($row);

            $data[$table] = $rows;
        }

        // Start clean, so photos that were replaced or deleted don't linger in the export.
        File::deleteDirectory(CatalogSeeder::path('media'));
        foreach (array_keys($files) as $path) {
            $target = CatalogSeeder::path("media/{$path}");
            File::ensureDirectoryExists(dirname($target));
            File::copy($disk->path($path), $target);
        }

        File::ensureDirectoryExists(CatalogSeeder::path());
        File::put(
            CatalogSeeder::path('catalog.json'),
            json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR)."\n",
        );

        $this->info(sprintf(
            'Saved %d collections, %d products, %d banners, %d coupons and %d files to %s',
            count($data['categories']),
            count($data['products']),
            count($data['banners']),
            count($data['coupons']),
            count($files),
            str_replace([base_path().DIRECTORY_SEPARATOR, '\\'], ['', '/'], CatalogSeeder::path()),
        ));
        $this->line('Commit that folder and push — a fresh install loads it automatically.');

        return self::SUCCESS;
    }
}
