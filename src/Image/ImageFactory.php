<?php

declare(strict_types=1);

namespace Pdf\Image;

use Pdf\Exception\ImageException;

/**
 * Loads and decodes an image, choosing a decoder by extension and falling back
 * to a content sniff.
 *
 * The source may be a filesystem path, an `http(s)://` URL, or an RFC 2397
 * `data:` URI. URL fetching uses the streams layer (`file_get_contents`) and,
 * if `allow_url_fopen` is disabled, `ext-curl` when present — no Composer
 * dependency either way. A remote fetch runs during layout; pass a URL only
 * when you trust it (there is no SSRF guard) and accept that output is no
 * longer byte-deterministic if the resource changes.
 *
 * Ports the type dispatch of `Image()` (fpdf.php:877-889), including the
 * `jpeg` -> `jpg` alias, and closes the code-review gap where an extension of
 * literally `"0"` was rejected.
 */
final class ImageFactory
{
    private const FETCH_TIMEOUT_SECONDS = 10;
    private const MAX_REDIRECTS = 5;

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

    private function fetch(string $url): string
    {
        $bytes = $this->fetchViaStreams($url);

        if ($bytes === null && (function_exists('curl_exec'))) {
            $bytes = $this->fetchViaCurl($url);
        }

        if ($bytes === null || $bytes === '') {
            throw new ImageException(sprintf(
                'Could not fetch image from %s. If allow_url_fopen is off and ext-curl is '
                . 'unavailable, fetch the bytes yourself and pass a data: URI to placeImageData().',
                $url,
            ));
        }

        return $bytes;
    }

    private function fetchViaStreams(string $url): ?string
    {
        if (!filter_var($url, FILTER_VALIDATE_URL)) {
            throw new ImageException('Not a valid image URL: ' . $url);
        }

        $options = [
            'method' => 'GET',
            'timeout' => self::FETCH_TIMEOUT_SECONDS,
            'follow_location' => 1,
            'max_redirects' => self::MAX_REDIRECTS,
            'header' => "Accept: image/*\r\nUser-Agent: declarative-pdf\r\n",
            'ignore_errors' => true,
        ];

        $context = stream_context_create([
            'http' => $options,
            'https' => $options,
            'ssl' => ['verify_peer' => true, 'verify_peer_name' => true],
        ]);

        $bytes = @file_get_contents($url, false, $context);
        if ($bytes === false) {
            return null;
        }

        // $http_response_header is populated by the HTTP(S) stream wrapper.
        if ($this->isHttpError($http_response_header)) {
            throw new ImageException('Image URL returned an HTTP error: ' . $url);
        }

        return $bytes;
    }

    private function fetchViaCurl(string $url): ?string
    {
        $handle = curl_init($url);
        if ($handle === false) {
            return null;
        }

        curl_setopt_array($handle, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_MAXREDIRS => self::MAX_REDIRECTS,
            CURLOPT_TIMEOUT => self::FETCH_TIMEOUT_SECONDS,
            CURLOPT_CONNECTTIMEOUT => 5,
            CURLOPT_USERAGENT => 'declarative-pdf',
        ]);

        $body = curl_exec($handle);
        $status = curl_getinfo($handle, CURLINFO_RESPONSE_CODE);
        curl_close($handle);

        if (!is_string($body) || $status >= 400) {
            return null;
        }

        return $body;
    }

    /**
     * @param list<string> $headers
     */
    private function isHttpError(array $headers): bool
    {
        $status = 0;
        foreach ($headers as $header) {
            if (preg_match('#^HTTP/\S+\s+(\d{3})#', $header, $m) === 1) {
                $status = (int) $m[1];
            }
        }

        return $status >= 400;
    }
}
