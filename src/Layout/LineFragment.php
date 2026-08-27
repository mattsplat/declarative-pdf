<?php

declare(strict_types=1);

namespace Pdf\Layout;

use Pdf\Color\Color;
use Pdf\Font\ResolvedFont;

/**
 * A piece of a laid-out visual line: either a maximal stretch of text drawn in
 * a single font/size/colour, or (when `imageIndex` is set) an inline image box.
 */
final readonly class LineFragment
{
    public function __construct(
        public string $text,
        public ?ResolvedFont $font,
        public float $fontSizePt,
        public Color $color,
        public float $widthPt,
        public ?string $link = null,
        public bool $underline = false,
        public bool $strikethrough = false,
        public float $baselineShiftPt = 0.0,
        public ?int $imageIndex = null,
        public float $imageHeightPt = 0.0,
    ) {
    }

    public function isImage(): bool
    {
        return $this->imageIndex !== null;
    }
}
