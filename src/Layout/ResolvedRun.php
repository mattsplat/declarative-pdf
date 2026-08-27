<?php

declare(strict_types=1);

namespace Pdf\Layout;

use Pdf\Color\Color;
use Pdf\Font\ResolvedFont;

/**
 * A text run whose style has been resolved to a concrete font, size and
 * colour — the input to {@see LineBreaker}.
 */
final readonly class ResolvedRun
{
    public function __construct(
        public string $text,
        public ResolvedFont $font,
        public float $fontSizePt,
        public Color $color,
        public ?string $link = null,
        public bool $underline = false,
        public bool $strikethrough = false,
        public float $baselineShiftPt = 0.0,
        /** When set, this run is an inline image rather than text. */
        public ?int $imageIndex = null,
        public float $imageWidthPt = 0.0,
        public float $imageHeightPt = 0.0,
    ) {
    }

    public function isImage(): bool
    {
        return $this->imageIndex !== null;
    }

    public function style(): RunStyle
    {
        return new RunStyle(
            $this->font,
            $this->fontSizePt,
            $this->color,
            $this->link,
            $this->underline,
            $this->strikethrough,
            $this->baselineShiftPt,
        );
    }
}
