<?php

declare(strict_types=1);

namespace Pdf\Layout;

use Pdf\Color\Color;
use Pdf\Font\ResolvedFont;

/**
 * The visual attributes shared by every {@see InlineItem} produced from one
 * inline run: font, size, colour, link target and text decorations.
 *
 * One instance per source run, so line-breaking can compare fragments for
 * "same run" identity with `===`.
 */
final readonly class RunStyle
{
    public function __construct(
        public ResolvedFont $font,
        public float $fontSizePt,
        public Color $color,
        public ?string $link = null,
        public bool $underline = false,
        public bool $strikethrough = false,
        public float $baselineShiftPt = 0.0,
    ) {
    }

    public function spaceWidthPt(): float
    {
        return $this->font->metrics->charAdvance(' ') * $this->fontSizePt / 1000.0;
    }

    public function widthOf(string $text): float
    {
        return $this->font->metrics->stringWidth($text, $this->fontSizePt);
    }
}
