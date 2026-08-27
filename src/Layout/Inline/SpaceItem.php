<?php

declare(strict_types=1);

namespace Pdf\Layout\Inline;

use Pdf\Layout\RunStyle;

/**
 * A single inter-word space — a break opportunity, and the unit that
 * justification stretches.
 */
final readonly class SpaceItem implements InlineItem
{
    public function __construct(
        public RunStyle $style,
        public float $widthPt,
    ) {
    }

    public static function of(RunStyle $style): self
    {
        return new self($style, $style->spaceWidthPt());
    }

    public function widthPt(): float
    {
        return $this->widthPt;
    }
}
