<?php

declare(strict_types=1);

namespace Pdf\Layout\Box;

use Pdf\Geometry\PathCommand;
use Pdf\Layout\Canvas;
use Pdf\Style\Paint;

/**
 * Vector linework at a fixed size. Never splits — half a shape on each of two
 * pages is never the intent, so it moves whole to the next page instead.
 */
final class PathBox extends AbstractBox
{
    /**
     * @param list<PathCommand> $commands coordinates relative to the box's top-left
     */
    public function __construct(
        private readonly array $commands,
        private readonly float $widthPt,
        private readonly float $heightPt,
        private readonly Paint $paint,
        private readonly float $marginBeforePt = 0.0,
        private readonly float $marginAfterPt = 0.0,
    ) {
    }

    public function contentHeightPt(): float
    {
        return $this->heightPt;
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
        return $this->heightPt <= $availableHeightPt + 1e-4 ? [$this, null] : [null, $this];
    }

    public function render(Canvas $canvas, float $xPt, float $yTopPt, float $widthPt): void
    {
        $canvas->path($this->commands, $xPt, $yTopPt, $this->paint);
    }

    public function minIntrinsicWidthPt(): float
    {
        return $this->widthPt;
    }

    public function maxIntrinsicWidthPt(): float
    {
        return $this->widthPt;
    }
}
