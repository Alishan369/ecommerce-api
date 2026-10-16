<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * Loads the catalogue saved by `php artisan catalog:export` (database/seeders/catalog) into an
 * empty store and copies its photos and videos onto the public disk.
 *
 * On a fresh database this is what
 * makes the live store open with its collections, products and images each time.
 * A store that already has collections is left alone, so a live catalogue is never overwritten.
 */
class CatalogSeeder extends Seeder
{
    /** Tables in insert order (parents first) → columns holding a public-disk path. */
    public const TABLES = [
        'scent_families' => ['image'],
        'categories' => ['image'],
        'products' => ['image'],
        'banners' => ['image', 'image_mobile', 'video'],
        'site_media' => ['image', 'video'],
        'coupons' => [],
    ];

    public static function path(string $file = ''): string
    {
        return database_path('seeders/catalog'.($file === '' ? '' : '/'.$file));
    }

    public static function isLocalFile(?string $path): bool
    {
        return $path !== null && $path !== '' && ! Str::startsWith($path, ['http://', 'https://']);
    }

    public function run(): void
    {
        $file = self::path('catalog.json');

        if (! File::exists($file)) {
            $this->command?->warn('No saved catalogue (php artisan catalog:export) — the store starts empty.');

            return;
        }

        if (DB::table('categories')->exists()) {
            $this->command?->info('The store already has collections — saved catalogue not loaded.');

            return;
        }

        $data = json_decode(File::get($file), true, flags: JSON_THROW_ON_ERROR);

        // Rows keep their exported ids, and a sub-collection may have a lower id than its parent.
        Schema::withoutForeignKeyConstraints(fn () => DB::transaction(function () use ($data) {
            foreach (array_keys(self::TABLES) as $table) {
                $rows = $data[$table] ?? [];
                if ($rows === []) {
                    continue;
                }

                // Tolerate columns added or dropped since the export.
                $columns = array_flip(Schema::getColumnListing($table));
                $rows = array_map(fn (array $row) => array_intersect_key($row, $columns), $rows);

                // The migration creates the default scent families; the export carries their edits and photos.
                if ($table === 'scent_families') {
                    DB::table($table)->delete();
                }

                foreach (array_chunk($rows, 100) as $chunk) {
                    DB::table($table)->insert($chunk);
                }
            }
        }));

        $copied = $this->copyMedia($data);

        $this->command?->info(sprintf(
            'Catalogue loaded: %d collections, %d products, %d banners, %d files.',
            count($data['categories'] ?? []),
            count($data['products'] ?? []),
            count($data['banners'] ?? []),
            $copied,
        ));
    }

    private function copyMedia(array $data): int
    {
        $disk = Storage::disk('public');
        $copied = 0;

        foreach (self::TABLES as $table => $mediaColumns) {
            foreach ($data[$table] ?? [] as $row) {
                foreach ($mediaColumns as $column) {
                    $path = $row[$column] ?? null;
                    $source = self::path("media/{$path}");

                    if (self::isLocalFile($path) && File::exists($source)) {
                        File::ensureDirectoryExists(dirname($disk->path($path)));
                        File::copy($source, $disk->path($path));
                        $copied++;
                    }
                }
            }
        }

        return $copied;
    }
}
