<?php

declare(strict_types=1);

namespace Pdf\Svg;

/**
 * A parsed SVG: its intrinsic size in points, and the shapes that fill it.
 *
 * Held at intrinsic size rather than at any particular placement size, so one
 * logo parsed once can be drawn at a different size on every page.
 */
final readonly class SvgDocument
{
    /** @param list<SvgShape> $shapes */
    public function __construct(
        public array $shapes,
        public float $widthPt,
        public float $heightPt,
    ) {
    }

    /**
     * The shapes scaled to fill a `$widthPt` x `$heightPt` box.
     *
     * @return list<SvgShape>
     */
    public function scaledTo(float $widthPt, float $heightPt): array
    {
        $scaleX = $this->widthPt > 0.0 ? $widthPt / $this->widthPt : 1.0;
        $scaleY = $this->heightPt > 0.0 ? $heightPt / $this->heightPt : 1.0;

        $shapes = [];
        foreach ($this->shapes as $shape) {
            $shapes[] = $shape->scaled($scaleX, $scaleY);
        }

        return $shapes;
    }
}
