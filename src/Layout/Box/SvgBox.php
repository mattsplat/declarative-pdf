<?php

declare(strict_types=1);

namespace Pdf\Layout\Box;

use Pdf\Layout\Canvas;
use Pdf\Style\Style;
use Pdf\Style\TextAlign;
use Pdf\Svg\SvgShape;

/**
 * A placed SVG. Never splits — half a logo on each of two pages is never the
 * intent, so it moves whole to the next page, as {@see ImageBox} does.
 *
 * The shapes arrive already scaled to `$widthPt`; every one is handed the same
 * box, because {@see \Pdf\Svg\SvgParser} expresses gradients as fractions of
 * the whole SVG rather than of each shape.
 */
final class SvgBox extends AbstractBox
{
    /** @param list<SvgShape> $shapes coordinates relative to the SVG's top-left */
    public function __construct(
        private readonly array $shapes,
        private readonly float $widthPt,
        private readonly float $heightPt,
        private readonly TextAlign $align,
        private readonly Style $style,
    ) {
    }

    public function contentHeightPt(): float
    {
        return $this->heightPt;
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
        return $this->heightPt <= $availableHeightPt + 1e-4 ? [$this, null] : [null, $this];
    }

    public function minIntrinsicWidthPt(): float
    {
        return $this->widthPt;
    }

    public function maxIntrinsicWidthPt(): float
    {
        return $this->widthPt;
    }

    public function render(Canvas $canvas, float $xPt, float $yTopPt, float $widthPt): void
    {
        $slack = max(0.0, $widthPt - $this->widthPt);
        $x = match ($this->align) {
            TextAlign::Right => $xPt + $slack,
            TextAlign::Center => $xPt + $slack / 2,
            default => $xPt,
        };

        foreach ($this->shapes as $shape) {
            $canvas->path($shape->commands, $x, $yTopPt, $shape->paint, $this->widthPt, $this->heightPt);
        }
    }
}
