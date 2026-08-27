<?php

declare(strict_types=1);

namespace Pdf\Image;

use Pdf\Exception\ImageException;

/**
 * Sequential reader over an in-memory byte string.
 *
 * Replaces FPDF's stream helpers `_readstream()` / `_readint()`
 * (fpdf.php:1439, 1456) — decoders now work on bytes, not file handles, so GIF
 * and WebP can be converted in memory.
 */
final class ByteReader
{
    private int $offset = 0;

    public function __construct(private readonly string $data)
    {
    }

    /** @phpstan-impure Advances the internal cursor. */
    public function bytes(int $n): string
    {
        if ($n < 0 || $this->offset + $n > strlen($this->data)) {
            throw new ImageException('Unexpected end of image data.');
        }
        $chunk = substr($this->data, $this->offset, $n);
        $this->offset += $n;

        return $chunk;
    }

    /** @phpstan-impure Advances the internal cursor. */
    public function uint32(): int
    {
        /** @var array{i: int} $parsed */
        $parsed = unpack('Ni', $this->bytes(4));

        return $parsed['i'];
    }

    /** @phpstan-impure Advances the internal cursor. */
    public function uint8(): int
    {
        return ord($this->bytes(1));
    }

    public function skip(int $n): void
    {
        $this->bytes($n);
    }
}
