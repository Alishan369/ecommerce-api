<?php

namespace App\Support;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * Product, category and banner images: a path on the public disk, or an
 * absolute URL for an externally hosted image (which is never deleted from here).
 * Uploads are compressed on the way in (see ImageOptimizer).
 */
final class StoredImage
{
    /** Longest side, in pixels, for product and category photos. */
    public const DEFAULT_MAX_SIZE = 1400;

    public static function url(?string $image): ?string
    {
        return match (true) {
            ! $image => null,
            self::isExternal($image) => $image,
            default => Storage::disk('public')->url($image),
        };
    }

    /**
     * Swaps an uploaded file in `$field` for its stored path, honours
     * `remove_{$field}`, and drops `$field` when neither was sent.
     * Images are compressed to WebP; a null `$maxSize` stores the file as uploaded (videos).
     */
    public static function fromInput(array $data, string $folder, string $field = 'image', ?int $maxSize = self::DEFAULT_MAX_SIZE): array
    {
        $image = $data[$field] ?? null;
        $removeKey = "remove_{$field}";

        if ($image instanceof UploadedFile) {
            $data[$field] = $maxSize === null
                ? $image->store($folder, 'public')
                : ImageOptimizer::store($image, $folder, $maxSize);
        } elseif (! empty($data[$removeKey])) {
            $data[$field] = null;
        } else {
            unset($data[$field]);
        }

        unset($data[$removeKey]);

        return $data;
    }

    /**
     * Updates the model from validated input and deletes any file a new image
     * (or `remove_*`) replaced. `$fields` maps attribute → max image size (null = store as uploaded).
     */
    public static function update(Model $model, array $data, string $folder, array $fields = ['image' => self::DEFAULT_MAX_SIZE]): void
    {
        $previous = [];
        foreach ($fields as $field => $maxSize) {
            $previous[$field] = $model->getAttribute($field);
            $data = self::fromInput($data, $folder, $field, $maxSize);
        }

        $model->update($data);

        foreach ($previous as $field => $old) {
            if (array_key_exists($field, $data) && $old !== $model->getAttribute($field)) {
                self::delete($old);
            }
        }
    }

    public static function delete(?string $image): void
    {
        if ($image && ! self::isExternal($image)) {
            Storage::disk('public')->delete($image);
        }
    }

    private static function isExternal(string $image): bool
    {
        return Str::startsWith($image, ['http://', 'https://']);
    }
}
