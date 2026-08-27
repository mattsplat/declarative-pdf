<?php

declare(strict_types=1);

namespace Pdf\Style;

use Pdf\Exception\PdfException;

/**
 * How one table column is sized.
 *
 *  - `auto()`    — sized from content, between its min and max intrinsic width
 *  - `fixed()`   — an explicit width in points
 *  - `fraction()`— shares leftover space by weight (like CSS `fr`)
 *
 * Optional `minPt` / `maxPt` clamp the resolved width. FPDF has no equivalent —
 * tuto5 hard-codes `array(40, 35, 40, 45)`.
 */
final readonly class ColumnWidth
{
    public const KIND_AUTO = 'auto';
    public const KIND_FIXED = 'fixed';
    public const KIND_FRACTION = 'fraction';

    private function __construct(
        public string $kind,
        public float $value,
        public ?float $minPt,
        public ?float $maxPt,
    ) {
    }

    public static function auto(?float $minPt = null, ?float $maxPt = null): self
    {
        return new self(self::KIND_AUTO, 0.0, $minPt, $maxPt);
    }

    public static function fixed(float $widthPt): self
    {
        if ($widthPt <= 0.0) {
            throw new PdfException('Fixed column width must be positive.');
        }

        return new self(self::KIND_FIXED, $widthPt, null, null);
    }

    public static function fraction(float $weight = 1.0, ?float $minPt = null, ?float $maxPt = null): self
    {
        if ($weight <= 0.0) {
            throw new PdfException('Fraction weight must be positive.');
        }

        return new self(self::KIND_FRACTION, $weight, $minPt, $maxPt);
    }

    public function isFixed(): bool
    {
        return $this->kind === self::KIND_FIXED;
    }

    public function isFraction(): bool
    {
        return $this->kind === self::KIND_FRACTION;
    }

    public function clamp(float $widthPt): float
    {
        if ($this->minPt !== null) {
            $widthPt = max($widthPt, $this->minPt);
        }
        if ($this->maxPt !== null) {
            $widthPt = min($widthPt, $this->maxPt);
        }

        return $widthPt;
    }
}
