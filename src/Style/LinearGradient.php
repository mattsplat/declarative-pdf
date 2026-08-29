<?php

declare(strict_types=1);

namespace Pdf\Style;

/**
 * An axial (linear) gradient: colour interpolated along the line from
 * (`$x0`, `$y0`) to (`$x1`, `$y1`), each a fraction of the path's box.
 */
final readonly class LinearGradient extends Gradient
{
    /** @param iterable<GradientStop> $stops */
    private function __construct(
        iterable $stops,
        public float $x0,
        public float $y0,
        public float $x1,
        public float $y1,
        GradientSpread $spread,
    ) {
        parent::__construct($stops, $spread);
    }

    /**
     * Left to right across the box.
     *
     * @param iterable<GradientStop> $stops
     */
    public static function horizontal(iterable $stops, GradientSpread $spread = GradientSpread::Pad): self
    {
        return new self($stops, 0.0, 0.5, 1.0, 0.5, $spread);
    }

    /**
     * Top to bottom down the box.
     *
     * @param iterable<GradientStop> $stops
     */
    public static function vertical(iterable $stops, GradientSpread $spread = GradientSpread::Pad): self
    {
        return new self($stops, 0.5, 0.0, 0.5, 1.0, $spread);
    }

    /**
     * An explicit axis, each coordinate a fraction of the box (0 = left / top).
     *
     * @param iterable<GradientStop> $stops
     */
    public static function between(
        iterable $stops,
        float $x0,
        float $y0,
        float $x1,
        float $y1,
        GradientSpread $spread = GradientSpread::Pad,
    ): self {
        return new self($stops, $x0, $y0, $x1, $y1, $spread);
    }

    public function shadingType(): int
    {
        return 2;
    }

    public function coords(\Closure $place, float $boxWidthPt, float $boxHeightPt): array
    {
        [$ax, $ay] = $place($this->x0 * $boxWidthPt, $this->y0 * $boxHeightPt);
        [$bx, $by] = $place($this->x1 * $boxWidthPt, $this->y1 * $boxHeightPt);

        return [$ax, $ay, $bx, $by];
    }
}
