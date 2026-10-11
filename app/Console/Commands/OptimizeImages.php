<?php

namespace App\Console\Commands;

use App\Models\Banner;
use App\Models\Category;
use App\Models\Product;
use App\Support\ImageOptimizer;
use App\Support\StoredImage;
use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * One-off: compresses images uploaded before upload compression existed.
 * New uploads are compressed automatically, so this only needs running once.
 *
 *   php artisan images:optimize --dry-run   # report only
 *   php artisan images:optimize
 */
class OptimizeImages extends Command
{
    protected $signature = 'images:optimize {--dry-run : Show what would change without touching anything}';

    protected $description = 'Convert previously uploaded product, category and banner images to compressed WebP';

    /** model class => [image attribute => longest side] */
    private const TARGETS = [
        Product::class => ['image' => StoredImage::DEFAULT_MAX_SIZE],
        Category::class => ['image' => StoredImage::DEFAULT_MAX_SIZE],
        Banner::class => ['image' => 2000, 'image_mobile' => 1350],
    ];

    public function handle(): int
    {
        if (! ImageOptimizer::supported()) {
            $this->error('PHP GD with WebP support is required.');

            return self::FAILURE;
        }

        $disk = Storage::disk('public');
        $dry = (bool) $this->option('dry-run');
        [$converted, $before, $after] = [0, 0, 0];

        foreach (self::TARGETS as $class => $fields) {
            $query = in_array('Illuminate\Database\Eloquent\SoftDeletes', class_uses_recursive($class), true)
                ? $class::withTrashed()
                : $class::query();

            $query->lazyById()->each(function (Model $model) use ($fields, $disk, $dry, &$converted, &$before, &$after) {
                foreach ($fields as $field => $maxSize) {
                    $path = $model->getAttribute($field);

                    if (! $path || Str::startsWith($path, ['http://', 'https://']) || Str::endsWith(strtolower($path), '.webp') || ! $disk->exists($path)) {
                        continue;
                    }

                    $webp = ImageOptimizer::compress($disk->path($path), $maxSize);
                    $oldSize = $disk->size($path);

                    if ($webp === null || strlen($webp) >= $oldSize) {
                        $this->line("  skip  {$path} (no saving)");

                        continue;
                    }

                    $newPath = dirname($path).'/'.pathinfo($path, PATHINFO_FILENAME).'.webp';
                    $this->line(sprintf('  %s  %s → %s  (%s → %s)', $dry ? 'would' : 'done ', $path, $newPath, $this->kb($oldSize), $this->kb(strlen($webp))));
                    [$converted, $before, $after] = [$converted + 1, $before + $oldSize, $after + strlen($webp)];

                    if ($dry) {
                        continue;
                    }

                    $disk->put($newPath, $webp);
                    $model->forceFill([$field => $newPath])->saveQuietly();
                    $disk->delete($path);
                }
            });
        }

        $this->info(sprintf('%s %d image(s): %s → %s.', $dry ? 'Would convert' : 'Converted', $converted, $this->kb($before), $this->kb($after)));

        return self::SUCCESS;
    }

    private function kb(int $bytes): string
    {
        return number_format($bytes / 1024).' KB';
    }
}
