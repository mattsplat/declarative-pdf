<?php

declare(strict_types=1);

namespace Pdf\Layout\Box;

use Pdf\Color\Color;
use Pdf\Font\ResolvedFont;
use Pdf\Layout\Canvas;

/**
 * One list item: a gutter-indented {@see StackBox} with a marker drawn against
 * the first line's baseline. The marker only appears on the first fragment of a
 * split item.
 */
final class ListItemBox extends AbstractBox
{
    public function __construct(
        private readonly StackBox $inner,
        private readonly float $gutterPt,
        private readonly string $marker,
        private readonly ResolvedFont $markerFont,
        private readonly float $markerSizePt,
        private readonly Color $markerColor,
        private readonly float $firstAscentPt,
        private readonly bool $showMarker,
        private readonly float $spacingAfterPt,
    ) {
    }

    public function hasForcedBreak(): bool
    {
        return $this->inner->hasForcedBreak();
    }

    public function contentHeightPt(): float
    {
        return $this->inner->contentHeightPt();
    }

    public function marginAfterPt(): float
    {
        return $this->spacingAfterPt;
    }

    public function split(float $availableHeightPt): array
    {
        [$head, $tail] = $this->inner->split($availableHeightPt);

        if ($head === null) {
            return [null, $this];
        }

        $headBox = new self(
            $head,
            $this->gutterPt,
            $this->marker,
            $this->markerFont,
            $this->markerSizePt,
            $this->markerColor,
            $this->firstAscentPt,
            $this->showMarker,
            0.0,
        );

        if ($tail === null) {
            return [$headBox, null];
        }

        $tailBox = new self(
            $tail,
            $this->gutterPt,
            '',
            $this->markerFont,
            $this->markerSizePt,
            $this->markerColor,
            $this->firstAscentPt,
            false,
            $this->spacingAfterPt,
        );

        return [$headBox, $tailBox];
    }

    public function minIntrinsicWidthPt(): float
    {
        return $this->gutterPt + $this->inner->minIntrinsicWidthPt();
    }

    public function maxIntrinsicWidthPt(): float
    {
        return $this->gutterPt + $this->inner->maxIntrinsicWidthPt();
    }

    public function render(Canvas $canvas, float $xPt, float $yTopPt, float $widthPt): void
    {
        if ($this->showMarker && $this->marker !== '') {
            $canvas->text(
                $this->marker,
                $xPt,
                $yTopPt + $this->firstAscentPt,
                $this->markerFont->index,
                $this->markerSizePt,
                $this->markerColor,
            );
        }

        $this->inner->render($canvas, $xPt + $this->gutterPt, $yTopPt, $widthPt - $this->gutterPt);
    }
}
