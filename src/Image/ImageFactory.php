<?php

declare(strict_types=1);

namespace Pdf\Image;

use Pdf\Exception\ImageException;
use Pdf\Exception\PdfException;
use Pdf\Support\RemoteBytes;

/**
 * Loads and decodes an image, choosing a decoder by extension and falling back
 * to a content sniff.
 *
 * The source may be a filesystem path, an `http(s)://` URL, or an RFC 2397
 * `data:` URI; {@see RemoteBytes} does the fetching.
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

    /**
     * Resolve an image from a path, an `http(s)://` URL, or a `data:` URI.
     */
    public function fromPath(string $source, ?string $type = null): ImageResource
    {
        if (str_starts_with($source, 'data:')) {
            return $this->fromDataUri($source);
        }

        if (str_starts_with($source, 'http://') || str_starts_with($source, 'https://')) {
            return $this->fromUrl($source);
        }

        if (isset($this->cache[$source])) {
            return $this->cache[$source];
        }

        if (!is_file($source) || !is_readable($source)) {
            throw new ImageException('Image file not found: ' . $source);
        }

        $bytes = (string) file_get_contents($source);
        $type ??= $this->detectType($source, $bytes);

        return $this->cache[$source] = $this->decode($bytes, $type, $source);
    }

    public function fromUrl(string $url): ImageResource
    {
        if (isset($this->cache[$url])) {
            return $this->cache[$url];
        }

        $bytes = $this->fetch($url);

        return $this->cache[$url] = $this->decode($bytes, $this->sniffType($bytes, $url), $url);
    }

    public function fromBytes(string $bytes, string $type, string $cacheKey): ImageResource
    {
        return $this->cache[$cacheKey] ??= $this->decode($bytes, $type, $cacheKey);
    }

    private function fromDataUri(string $uri): ImageResource
    {
        $comma = strpos($uri, ',');
        if ($comma === false) {
            throw new ImageException('Malformed data: URI (no comma).');
        }

        $meta = substr($uri, 5, $comma - 5);
        $payload = substr($uri, $comma + 1);

        if (str_contains($meta, ';base64')) {
            $decoded = base64_decode($payload, true);
            $bytes = $decoded === false ? '' : $decoded;
        } else {
            $bytes = rawurldecode($payload);
        }

        if ($bytes === '') {
            throw new ImageException('Empty or undecodable data: URI.');
        }

        $key = 'data:sha1:' . sha1($bytes);

        return $this->cache[$key] ??= $this->decode($bytes, $this->sniffType($bytes, 'data: URI'), $key);
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
            return $extension === 'jpeg' ? 'jpg' : $extension;
        }

        return $this->sniffType($bytes, $path);
    }

    private function sniffType(string $bytes, string $source): string
    {
        return match (true) {
            str_starts_with($bytes, "\xFF\xD8\xFF") => 'jpg',
            str_starts_with($bytes, "\x89PNG\r\n\x1a\n") => 'png',
            str_starts_with($bytes, 'GIF8') => 'gif',
            strlen($bytes) >= 12 && substr($bytes, 0, 4) === 'RIFF' && substr($bytes, 8, 4) === 'WEBP' => 'webp',
            default => throw new ImageException(sprintf(
                'Could not recognise %s as JPEG/PNG/GIF/WebP (got %d bytes; a URL may have '
                . 'returned an error page).',
                $source,
                strlen($bytes),
            )),
        };
    }

    /**
     * The shared transport, rewrapped so callers keep seeing an
     * {@see ImageException} for anything image-related that goes wrong.
     */
    private function fetch(string $url): string
    {
        try {
            return RemoteBytes::fetch($url, 'image/*');
        } catch (PdfException $exception) {
            throw new ImageException($exception->getMessage(), 0, $exception);
        }
    }
}
