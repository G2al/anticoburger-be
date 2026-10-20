<?php

namespace App\Services\Menu;

use GdImage;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Throwable;

/**
 * Shrinks menu photos: fixes the phone orientation, limits the longest side and
 * re-encodes as WebP. Uses GD only. If the image cannot be processed (unsupported
 * format, missing WebP support) the original file is kept untouched.
 */
class MenuImageOptimizer
{
    public const MAX_SIDE = 1200;

    public const WEBP_QUALITY = 80;

    /**
     * @return string the path of the optimized image (or the original path when it was kept)
     */
    public function optimize(string $disk, string $path): string
    {
        if (str_ends_with(strtolower($path), '.webp') || ! function_exists('imagewebp')) {
            return $path;
        }

        $storage = Storage::disk($disk);

        try {
            $image = @imagecreatefromstring((string) $storage->get($path));

            if (! $image instanceof GdImage) {
                return $path;
            }

            $image = $this->fixOrientation($image, $storage->path($path));
            $image = $this->limitSize($image);

            imagepalettetotruecolor($image);
            imagealphablending($image, false);
            imagesavealpha($image, true);

            ob_start();
            $encoded = imagewebp($image, null, self::WEBP_QUALITY);
            $webp = (string) ob_get_clean();

            if (! $encoded || $webp === '') {
                return $path;
            }

            $optimizedPath = Str::beforeLast($path, '.').'.webp';
            $storage->put($optimizedPath, $webp);
            $storage->delete($path);

            return $optimizedPath;
        } catch (Throwable $exception) {
            Log::warning('Menu image not optimized.', ['path' => $path, 'exception' => $exception::class]);

            return $path;
        }
    }

    private function limitSize(GdImage $image): GdImage
    {
        $width = imagesx($image);
        $height = imagesy($image);
        $scale = self::MAX_SIDE / max($width, $height);

        if ($scale >= 1) {
            return $image;
        }

        return imagescale($image, (int) round($width * $scale), (int) round($height * $scale), IMG_BICUBIC) ?: $image;
    }

    /**
     * Phone photos are often stored sideways with an EXIF orientation flag.
     */
    private function fixOrientation(GdImage $image, string $absolutePath): GdImage
    {
        if (! function_exists('exif_read_data')) {
            return $image;
        }

        $orientation = (int) (@exif_read_data($absolutePath)['Orientation'] ?? 1);

        $rotated = match ($orientation) {
            3 => imagerotate($image, 180, 0),
            6 => imagerotate($image, -90, 0),
            8 => imagerotate($image, 90, 0),
            default => $image,
        };

        return $rotated ?: $image;
    }
}
