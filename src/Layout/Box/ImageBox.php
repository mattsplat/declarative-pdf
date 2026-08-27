<?php

declare(strict_types=1);

namespace Pdf\Layout\Box;

use Pdf\Layout\Canvas;
use Pdf\Style\Style;
use Pdf\Style\TextAlign;

/**
 * A placed image. Never splits — if it is taller than the page it overflows
 * (the paginator forces it onto its own sheet).
 */
final class ImageBox extends AbstractBox
{
    public function __construct(
        private readonly int $imageIndex,
        private readonly float $imageWidthPt,
        private readonly float $imageHeightPt,
        private readonly TextAlign $align,
        private readonly Style $style,
    ) {
    }

    public function contentHeightPt(): float
    {
        return $this->imageHeightPt;
    }

    public function marginBeforePt(): float
    {
        return $this->style->spaceBeforePt;
    }

    public function marginAfterPt(): float
    {
        return $this->style->spaceAfterPt;
    }

    public function split(float $availableHeightPt): array
    {
        return $this->imageHeightPt <= $availableHeightPt + 1e-4 ? [$this, null] : [null, $this];
    }

    public function minIntrinsicWidthPt(): float
    {
        return $this->imageWidthPt;
    }

    public function maxIntrinsicWidthPt(): float
    {
        return $this->imageWidthPt;
    }

    public function render(Canvas $canvas, float $xPt, float $yTopPt, float $widthPt): void
    {
        $slack = max(0.0, $widthPt - $this->imageWidthPt);
        $x = match ($this->align) {
            TextAlign::Right => $xPt + $slack,
            TextAlign::Center => $xPt + $slack / 2,
            default => $xPt,
        };

        $canvas->image($this->imageIndex, $x, $yTopPt, $this->imageWidthPt, $this->imageHeightPt);
    }
}
