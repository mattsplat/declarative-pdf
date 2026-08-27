<?php

declare(strict_types=1);

namespace Pdf\Layout\Inline;

/**
 * A fixed-size inline box — currently an inline image. Behaves like an
 * unbreakable word: break opportunities exist only at adjacent spaces.
 */
final readonly class BoxItem implements InlineItem
{
    public function __construct(
        public int $imageIndex,
        public float $widthPt,
        public float $heightPt,
        /** Distance from the box's top to the text baseline it sits on. */
        public float $ascentPt,
        public float $fontSizePt,
        public ?string $link = null,
        public float $baselineShiftPt = 0.0,
    ) {
    }

    public function widthPt(): float
    {
        return $this->widthPt;
    }
}
