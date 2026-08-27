<?php

declare(strict_types=1);

namespace Pdf\Font;

/**
 * Glyph-advance lookup for one font.
 *
 * `stringWidth()` ports `GetStringWidth()` (fpdf.php:405-415): sum the advance
 * of each byte, scale by size / 1000. This is the hot path of the layout
 * engine, so it stays a tight loop over raw bytes.
 */
final readonly class FontMetrics
{
    /** @param array<int, int> $charWidths 256 advances indexed by byte value */
    public function __construct(private array $charWidths)
    {
    }

    /** Width of a single-byte-encoded string at the given point size. */
    public function stringWidth(string $text, float $sizePt): float
    {
        $units = 0;
        $length = strlen($text);
        for ($i = 0; $i < $length; $i++) {
            $units += $this->charWidths[ord($text[$i])] ?? 0;
        }

        return $units * $sizePt / 1000.0;
    }

    /** Advance of one byte, in 1/1000 em. */
    public function charAdvance(string $byte): int
    {
        return $this->charWidths[ord($byte)] ?? 0;
    }
}
