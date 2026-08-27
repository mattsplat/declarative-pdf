<?php

declare(strict_types=1);

namespace Pdf\Layout\Box;

use Pdf\Layout\Canvas;

/**
 * A zero-height marker that records where a named destination lands.
 */
final class AnchorBox extends AbstractBox
{
    public function __construct(private readonly string $name)
    {
    }

    public function contentHeightPt(): float
    {
        return 0.0;
    }

    /**
     * An anchor must always travel to the same page as the content it marks,
     * so a page break never strands it behind on the previous page.
     */
    public function keepWithNext(): bool
    {
        return true;
    }

    public function split(float $availableHeightPt): array
    {
        return [$this, null];
    }

    public function render(Canvas $canvas, float $xPt, float $yTopPt, float $widthPt): void
    {
        $canvas->anchor($this->name, $yTopPt);
    }
}
