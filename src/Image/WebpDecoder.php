<?php

declare(strict_types=1);

namespace Pdf\Image;

use Pdf\Exception\ImageException;

/**
 * Ported from `_parsewebp()` (fpdf.php:1487) — but fixed per code review:
 *
 * FPDF converts WebP to JPEG, which drops the alpha channel (compositing
 * transparency onto black) and lossily recompresses. Here we re-encode as PNG
 * in memory and route through {@see PngDecoder}, exactly as the GIF path does,
 * so transparency and fidelity are preserved and no temp file is created.
 */
final class WebpDecoder
{
    public function __construct(private readonly PngDecoder $png = new PngDecoder())
    {
    }

    public function decode(string $bytes, string $cacheKey): ImageResource
    {
        if (!function_exists('imagecreatefromstring') || !function_exists('imagepng')) {
            throw new ImageException('The GD extension is required for WebP support.');
        }

        $image = @imagecreatefromstring($bytes);
        if ($image === false) {
            throw new ImageException('Missing or incorrect WebP file: ' . $cacheKey);
        }

        imagepalettetotruecolor($image);
        imagealphablending($image, false);
        imagesavealpha($image, true);

        ob_start();
        imagepng($image);
        $pngBytes = (string) ob_get_clean();
        imagedestroy($image);

        return $this->png->decode($pngBytes, $cacheKey);
    }
}
