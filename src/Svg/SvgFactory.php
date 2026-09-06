<?php

declare(strict_types=1);

namespace Pdf\Svg;

use Pdf\Exception\PdfException;
use Pdf\Exception\SvgException;
use Pdf\Support\RemoteBytes;

/**
 * Loads and parses an SVG, mirroring {@see \Pdf\Image\ImageFactory}: the source
 * may be a filesystem path, an `http(s)://` URL, or an RFC 2397 `data:` URI.
 *
 * Results are cached by source, and held at intrinsic size rather than at any
 * placement size, so a logo repeated on every page of a long document is read
 * and parsed exactly once.
 */
final class SvgFactory
{
    /** gzip's magic number: an `.svgz` is a deflated SVG. */
    private const GZIP_MAGIC = "\x1f\x8b";

    /** @var array<string, SvgDocument> */
    private array $cache = [];

    public function __construct(private readonly SvgParser $parser = new SvgParser())
    {
    }

    public function fromPath(string $source): SvgDocument
    {
        if (isset($this->cache[$source])) {
            return $this->cache[$source];
        }

        return $this->cache[$source] = $this->parse($this->bytes($source), $source);
    }

    /** Parse markup already in memory, cached under a hash of its bytes. */
    public function fromString(string $markup): SvgDocument
    {
        $key = 'svg:sha1:' . sha1($markup);

        return $this->cache[$key] ??= $this->parse($markup, 'the given markup');
    }

    private function parse(string $bytes, string $source): SvgDocument
    {
        if (str_starts_with($bytes, self::GZIP_MAGIC)) {
            $inflated = @gzdecode($bytes);
            if ($inflated === false) {
                throw new SvgException('Could not decompress SVGZ: ' . $source);
            }
            $bytes = $inflated;
        }

        try {
            return $this->parser->parse($bytes);
        } catch (SvgException $exception) {
            // The parser has no idea which file it was handed; say so here.
            throw new SvgException(sprintf('%s (in %s)', $exception->getMessage(), $source), 0, $exception);
        }
    }

    private function bytes(string $source): string
    {
        if (str_starts_with($source, 'data:')) {
            return self::fromDataUri($source);
        }

        if (str_starts_with($source, 'http://') || str_starts_with($source, 'https://')) {
            try {
                return RemoteBytes::fetch($source, 'image/svg+xml');
            } catch (PdfException $exception) {
                throw new SvgException($exception->getMessage(), 0, $exception);
            }
        }

        if (!is_file($source) || !is_readable($source)) {
            throw new SvgException('SVG file not found: ' . $source);
        }

        return (string) file_get_contents($source);
    }

    private static function fromDataUri(string $uri): string
    {
        $comma = strpos($uri, ',');
        if ($comma === false) {
            throw new SvgException('Malformed data: URI (no comma).');
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
            throw new SvgException('Empty or undecodable data: URI.');
        }

        return $bytes;
    }
}
