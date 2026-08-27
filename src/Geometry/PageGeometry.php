<?php

declare(strict_types=1);

namespace Pdf\Geometry;

/**
 * Resolved geometry for a single page, entirely in PostScript points.
 *
 * The layout engine and renderer work exclusively in points; user-supplied
 * values in other units are converted at the API boundary. This is equivalent
 * to FPDF scaling every coordinate by `$k` at emit time (e.g. fpdf.php:639),
 * but keeps a single coordinate system throughout.
 */
final readonly class PageGeometry
{
    public function __construct(
        public PageSize $size,
        public Orientation $orientation,
        public Edges $marginsPt,
    ) {
    }

    public function widthPt(): float
    {
        return $this->size->widthPt;
    }

    public function heightPt(): float
    {
        return $this->size->heightPt;
    }

    /** The area available for flowing content, inside the margins. */
    public function contentBox(): Rect
    {
        return new Rect(
            $this->marginsPt->left,
            $this->marginsPt->top,
            $this->widthPt() - $this->marginsPt->horizontal(),
            $this->heightPt() - $this->marginsPt->vertical(),
        );
    }

    public function contentWidthPt(): float
    {
        return $this->widthPt() - $this->marginsPt->horizontal();
    }

    /**
     * Convert a top-left user-space Y to a PDF bottom-left Y.
     *
     * This is the ONLY place the Y axis is flipped. Ports the pervasive
     * `$this->h - $y` idiom (fpdf.php:611, 618, 639, 928, 1280, 1568).
     */
    public function flipY(float $yTop): float
    {
        return $this->heightPt() - $yTop;
    }
}
