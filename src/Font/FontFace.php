<?php

declare(strict_types=1);

namespace Pdf\Font;

/**
 * One cut of a font family: a numeric weight plus a slope.
 *
 * Weights follow the CSS/OpenType 100–900 scale, so a family can expose Light,
 * Semibold or Black as first-class cuts rather than squeezing everything into
 * FPDF's four `''`/`'B'`/`'I'`/`'BI'` styles (fpdf.php:490). The legacy
 * {@see FontStyle} enum maps onto the 400/700 pair via {@see self::fromLegacy()}.
 */
final readonly class FontFace
{
    public function __construct(
        public int $weight = 400,
        public bool $italic = false,
    ) {
    }

    public static function regular(): self
    {
        return new self();
    }

    public static function bold(): self
    {
        return new self(700);
    }

    public static function italic(): self
    {
        return new self(400, true);
    }

    public static function boldItalic(): self
    {
        return new self(700, true);
    }

    public static function fromLegacy(FontStyle $style): self
    {
        return new self($style->isBold() ? 700 : 400, $style->isItalic());
    }

    /**
     * Whether this cut reads as bold. The threshold is CSS's: 600 (Semibold)
     * and up. Which file actually gets used is {@see FontRepository}'s
     * nearest-weight search, not this.
     */
    public function isBold(): bool
    {
        return $this->weight >= 600;
    }

    public function equals(self $other): bool
    {
        return $this->weight === $other->weight && $this->italic === $other->italic;
    }

    /** Stable identity for cache and registration keys. */
    public function key(): string
    {
        return $this->weight . ':' . ($this->italic ? 'i' : 'r');
    }

    public function describe(): string
    {
        return $this->weight . ($this->italic ? ' italic' : '');
    }
}
