<?php

declare(strict_types=1);

namespace Pdf\Image;

use Pdf\Exception\ImageException;

/**
 * Ported from `_parsegif()` (fpdf.php:1463): decode with GD, re-encode as PNG
 * in memory, then hand off to {@see PngDecoder}.
 */
final class GifDecoder
{
    public function __construct(private readonly PngDecoder $png = new PngDecoder())
    {
    }

    public function decode(string $bytes, string $cacheKey): ImageResource
    {
        if (!function_exists('imagecreatefromstring') || !function_exists('imagepng')) {
            throw new ImageException('The GD extension is required for GIF support.');
        }

        $image = @imagecreatefromstring($bytes);
        if ($image === false) {
            throw new ImageException('Missing or incorrect GIF file: ' . $cacheKey);
        }

        imageinterlace($image, false);
        ob_start();
        imagepng($image);
        $pngBytes = (string) ob_get_clean();
        imagedestroy($image);

        return $this->png->decode($pngBytes, $cacheKey);
    }
}
