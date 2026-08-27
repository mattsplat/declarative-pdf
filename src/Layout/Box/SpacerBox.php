<?php

declare(strict_types=1);

namespace Pdf\Layout\Box;

use Pdf\Layout\Canvas;

/**
 * Fixed vertical whitespace. Splits freely — whitespace across a page break is
 * simply divided.
 */
final class SpacerBox extends AbstractBox
{
    private const EPSILON = 1e-4;

    public function __construct(private readonly float $heightPt)
    {
    }

    public function contentHeightPt(): float
    {
        return $this->heightPt;
    }

    public function split(float $availableHeightPt): array
    {
        if ($this->heightPt <= $availableHeightPt + self::EPSILON) {
            return [$this, null];
        }
        if ($availableHeightPt <= self::EPSILON) {
            return [null, $this];
        }

        return [new self($availableHeightPt), new self($this->heightPt - $availableHeightPt)];
    }

    public function render(Canvas $canvas, float $xPt, float $yTopPt, float $widthPt): void
    {
        // Nothing to draw.
    }
}
