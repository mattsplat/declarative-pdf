<?php

declare(strict_types=1);

namespace Pdf\Layout\Box;

use Pdf\Layout\Canvas;

/**
 * A zero-height marker that forces {@see StackBox} to end the current page.
 */
final class PageBreakBox extends AbstractBox
{
    public function contentHeightPt(): float
    {
        return 0.0;
    }

    public function hasForcedBreak(): bool
    {
        return true;
    }

    public function split(float $availableHeightPt): array
    {
        return [null, $this];
    }

    public function render(Canvas $canvas, float $xPt, float $yTopPt, float $widthPt): void
    {
    }
}
