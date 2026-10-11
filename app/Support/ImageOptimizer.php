<?php

namespace App\Support;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * Stores uploaded images compressed: resized to fit `$maxSize` on the longest
 * side, phone-camera rotation applied, re-encoded as WebP (transparency kept).
 *
 * Needs GD with WebP. Without it — or for an image GD can't read, or one too
 * large to process safely — the original file is stored unchanged, so an
 * upload never fails because of compression.
 */
final class ImageOptimizer
{
    /** Above this many pixels the image is stored as-is rather than risk running out of memory. */
    private const MAX_PIXELS = 40_000_000;

    public static function store(UploadedFile $file, string $folder, int $maxSize = 1600, int $quality = 80): string
    {
        if (! self::supported()) {
            return $file->store($folder, 'public');
        }

        $webp = self::compress($file->getRealPath(), $maxSize, $quality);

        if ($webp === null) {
            return $file->store($folder, 'public');
        }

        $path = trim($folder, '/').'/'.Str::random(40).'.webp';
        Storage::disk('public')->put($path, $webp);

        return $path;
    }

    /** WebP bytes for the image at `$path`, or null when it should be kept as it is. */
    public static function compress(string $path, int $maxSize = 1600, int $quality = 80): ?string
    {
        if (! self::supported()) {
            return null;
        }

        try {
            return self::encode($path, $maxSize, $quality);
        } catch (\Throwable $e) {
            Log::warning('Image optimisation failed; keeping the original.', ['error' => $e->getMessage()]);

            return null;
        }
    }

    public static function supported(): bool
    {
        return function_exists('imagewebp') && function_exists('imagecreatefromstring');
    }

    /** Returns WebP bytes, or null when the file should be kept as uploaded. */
    private static function encode(string $path, int $maxSize, int $quality): ?string
    {
        $size = @getimagesize($path);
        if (! $size || $size[0] * $size[1] > self::MAX_PIXELS) {
            return null;
        }

        self::ensureMemory($size[0] * $size[1]);

        $source = @imagecreatefromstring((string) file_get_contents($path));
        if (! $source) {
            return null;
        }

        $source = self::applyExifRotation($source, $path, $size[2] ?? null);

        $width = imagesx($source);
        $height = imagesy($source);
        $scale = min(1, $maxSize / max($width, $height));
        $targetW = max(1, (int) round($width * $scale));
        $targetH = max(1, (int) round($height * $scale));

        $canvas = imagecreatetruecolor($targetW, $targetH);
        imagealphablending($canvas, false);
        imagesavealpha($canvas, true);
        imagefill($canvas, 0, 0, imagecolorallocatealpha($canvas, 0, 0, 0, 127));
        imagecopyresampled($canvas, $source, 0, 0, 0, 0, $targetW, $targetH, $width, $height);
        unset($source); // GdImage is freed when released (imagedestroy is a no-op since PHP 8)

        ob_start();
        $ok = imagewebp($canvas, null, $quality);
        $bytes = ob_get_clean();
        unset($canvas);

        return $ok && $bytes !== '' ? $bytes : null;
    }

    /** JPEGs from phones store "rotate me" in EXIF instead of rotating the pixels. */
    private static function applyExifRotation(\GdImage $image, string $path, ?int $type): \GdImage
    {
        if ($type !== IMAGETYPE_JPEG || ! function_exists('exif_read_data')) {
            return $image;
        }

        $orientation = (int) (@exif_read_data($path)['Orientation'] ?? 1);
        $angle = match ($orientation) {
            3 => 180,
            6 => -90,
            8 => 90,
            default => 0,
        };

        if ($angle === 0) {
            return $image;
        }

        $rotated = imagerotate($image, $angle, 0);
        if ($rotated === false) {
            return $image;
        }
        unset($image);

        return $rotated;
    }

    /** Decoding needs ~5 bytes per pixel; raise the limit for this request if it's too low. */
    private static function ensureMemory(int $pixels): void
    {
        $limit = ini_get('memory_limit');
        if ($limit === false || $limit === '-1') {
            return;
        }

        $current = self::toBytes($limit);
        $needed = memory_get_usage() + $pixels * 6 + 32 * 1024 * 1024;

        if ($needed > $current) {
            @ini_set('memory_limit', (string) min($needed, 512 * 1024 * 1024));
        }
    }

    private static function toBytes(string $value): int
    {
        $number = (int) $value;

        return match (strtolower(substr(trim($value), -1))) {
            'g' => $number * 1024 ** 3,
            'm' => $number * 1024 ** 2,
            'k' => $number * 1024,
            default => $number,
        };
    }
}
