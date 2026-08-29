<?php

declare(strict_types=1);

namespace Pdf\Layout;

use Pdf\Color\Color;

/**
 * The drawing surface a {@see Box} renders onto. All coordinates are in points
 * with a top-left origin; the implementation is responsible for the Y flip.
 *
 * Implemented by {@see \Pdf\Render\ContentStream}.
 */
interface Canvas
{
    public function text(
        string $text,
        float $xPt,
        float $baselineYFromTopPt,
        int $fontIndex,
        float $sizePt,
        Color $color,
        ?float $wordSpacingPt = null,
    ): void;

    public function fillRect(float $xPt, float $yTopPt, float $widthPt, float $heightPt, Color $color): void;

    public function strokeEdges(
        float $xPt,
        float $yTopPt,
        float $widthPt,
        float $heightPt,
        \Pdf\Geometry\Edges $edgeWidthsPt,
        Color $color,
    ): void;

    public function horizontalLine(float $x1Pt, float $x2Pt, float $yPt, float $lineWidthPt, Color $color): void;

    /**
     * Draw arbitrary linework. Command coordinates are relative to
     * ($xPt, $yTopPt) and painted in one `q … Q` group. `$boxWidthPt` /
     * `$boxHeightPt` are the path's own box, used to resolve a gradient fill.
     *
     * @param list<\Pdf\Geometry\PathCommand> $commands
     */
    public function path(
        array $commands,
        float $xPt,
        float $yTopPt,
        \Pdf\Style\Paint $paint,
        float $boxWidthPt = 0.0,
        float $boxHeightPt = 0.0,
    ): void;

    /**
     * Run $draw with $commands (relative to ($xPt, $yTopPt)) as the clip region.
     *
     * @param list<\Pdf\Geometry\PathCommand> $commands
     */
    public function withPathClip(
        array $commands,
        float $xPt,
        float $yTopPt,
        \Pdf\Style\FillRule $rule,
        \Closure $draw,
    ): void;

    /** Draw image resource `/I{imageIndex}` into the given box. */
    public function image(int $imageIndex, float $xPt, float $yTopPt, float $widthPt, float $heightPt): void;

    /**
     * Record a clickable rectangle. `$target` is a URI string, or an
     * `#name` internal anchor reference.
     */
    public function link(float $xPt, float $yTopPt, float $widthPt, float $heightPt, string $target): void;

    /** Record that a named destination anchor sits at this y on the page. */
    public function anchor(string $name, float $yTopPt): void;
}
