<?php

declare(strict_types=1);

namespace Pdf\Image;

use Pdf\Exception\ImageException;

/**
 * Loads and decodes an image file, choosing a decoder by extension and falling
 * back to a content sniff.
 *
 * Ports the type dispatch of `Image()` (fpdf.php:877-889), including the
 * `jpeg` -> `jpg` alias, and closes the code-review gap where an extension of
 * literally `"0"` was rejected.
 */
final class ImageFactory
{
    /** @var array<string, ImageResource> */
    private array $cache = [];

    public function __construct(
        private readonly JpegDecoder $jpeg = new JpegDecoder(),
        private readonly PngDecoder $png = new PngDecoder(),
        private readonly GifDecoder $gif = new GifDecoder(),
        private readonly WebpDecoder $webp = new WebpDecoder(),
    ) {
    }

    public function fromPath(string $path, ?string $type = null): ImageResource
    {
        if (isset($this->cache[$path])) {
            return $this->cache[$path];
        }

        if (!is_file($path) || !is_readable($path)) {
            throw new ImageException('Image file not found: ' . $path);
        }

        $bytes = (string) file_get_contents($path);
        $type ??= $this->detectType($path, $bytes);

        return $this->cache[$path] = $this->decode($bytes, $type, $path);
    }

    public function fromBytes(string $bytes, string $type, string $cacheKey): ImageResource
    {
        return $this->cache[$cacheKey] ??= $this->decode($bytes, $type, $cacheKey);
    }

    private function decode(string $bytes, string $type, string $cacheKey): ImageResource
    {
        return match (strtolower($type)) {
            'jpg', 'jpeg' => $this->jpeg->decode($bytes, $cacheKey),
            'png' => $this->png->decode($bytes, $cacheKey),
            'gif' => $this->gif->decode($bytes, $cacheKey),
            'webp' => $this->webp->decode($bytes, $cacheKey),
            default => throw new ImageException('Unsupported image type: ' . $type),
        };
    }

    private function detectType(string $path, string $bytes): string
    {
        $extension = strtolower(pathinfo($path, PATHINFO_EXTENSION));
        if ($extension !== '') {
            return $extension;
        }

        return match (true) {
            str_starts_with($bytes, "\xFF\xD8\xFF") => 'jpg',
            str_starts_with($bytes, "\x89PNG\r\n\x1a\n") => 'png',
            str_starts_with($bytes, 'GIF8') => 'gif',
            strlen($bytes) >= 12 && substr($bytes, 0, 4) === 'RIFF' && substr($bytes, 8, 4) === 'WEBP' => 'webp',
            default => throw new ImageException('Cannot determine image type of ' . $path),
        };
    }
}
