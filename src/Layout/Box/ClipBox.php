<?php

declare(strict_types=1);

namespace Pdf\Layout\Box;

use Pdf\Geometry\PathCommand;
use Pdf\Layout\Canvas;
use Pdf\Style\FillRule;

/**
 * Renders a nested stack inside a path clip. Like {@see PathBox} it is a
 * fixed-size figure that never splits: it moves whole to the next page rather
 * than leaving half a clipped drawing behind.
 */
final class ClipBox extends AbstractBox
{
    /**
     * @param list<PathCommand> $clipCommands coordinates relative to the box's top-left
     */
    public function __construct(
        private readonly array $clipCommands,
        private readonly float $widthPt,
        private readonly float $heightPt,
        private readonly FillRule $clipRule,
        private readonly StackBox $inner,
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
        // Children were measured against the clip path's own width, so they must
        // render at that width too — not the wider column they sit in.
        $inner = $this->inner;
        $innerWidth = $this->widthPt;
        $canvas->withPathClip(
            $this->clipCommands,
            $xPt,
            $yTopPt,
            $this->clipRule,
            static function () use ($inner, $canvas, $xPt, $yTopPt, $innerWidth): void {
                $inner->render($canvas, $xPt, $yTopPt, $innerWidth);
            },
        );
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
