<?php

declare(strict_types=1);

namespace Pdf\Layout\Box;

use Pdf\Color\Color;
use Pdf\Layout\Canvas;

/**
 * A horizontal rule. Never splits.
 */
final class RuleBox extends AbstractBox
{
    public function __construct(
        private readonly float $thicknessPt,
        private readonly Color $color,
        private readonly float $marginBeforePt,
        private readonly float $marginAfterPt,
    ) {
    }

    public function contentHeightPt(): float
    {
        return $this->thicknessPt;
    }

    public function marginBeforePt(): float
    {
        return $this->marginBeforePt;
    }

    public function marginAfterPt(): float
    {
        return $this->marginAfterPt;
    }

    public function split(float $availableHeightPt): array
    {
        return $this->thicknessPt <= $availableHeightPt + 1e-4 ? [$this, null] : [null, $this];
    }

    public function render(Canvas $canvas, float $xPt, float $yTopPt, float $widthPt): void
    {
        $canvas->horizontalLine(
            $xPt,
            $xPt + $widthPt,
            $yTopPt + $this->thicknessPt / 2,
            $this->thicknessPt,
            $this->color,
        );
    }
}
