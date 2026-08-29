<?php

declare(strict_types=1);

namespace Pdf\Style;

/**
 * A radial gradient: colour interpolated between two circles. The first stop
 * is at the inner circle (centred on the focal point, radius `$innerRadius`),
 * the last at the outer circle (centred on (`$cx`, `$cy`), radius `$radius`).
 *
 * Centre and focal point are fractions of the box; the radii are fractions of
 * its larger dimension.
 */
final readonly class RadialGradient extends Gradient
{
    /** @param iterable<GradientStop> $stops */
    private function __construct(
        iterable $stops,
        public float $cx,
        public float $cy,
        public float $radius,
        public float $innerRadius,
        public float $focalX,
        public float $focalY,
        GradientSpread $spread,
    ) {
        parent::__construct($stops, $spread);
    }

    /**
     * A circle of radius `$radius` (fraction of the larger box dimension)
     * centred at (`$cx`, `$cy`) (fractions of the box).
     *
     * @param iterable<GradientStop> $stops
     */
    public static function centered(
        iterable $stops,
        float $cx = 0.5,
        float $cy = 0.5,
        float $radius = 0.5,
        GradientSpread $spread = GradientSpread::Pad,
    ): self {
        return new self($stops, $cx, $cy, $radius, 0.0, $cx, $cy, $spread);
    }

    /**
     * As {@see self::centered()} but with the inner circle offset to
     * (`$focalX`, `$focalY`) for an off-centre highlight.
     *
     * @param iterable<GradientStop> $stops
     */
    public static function focused(
        iterable $stops,
        float $focalX,
        float $focalY,
        float $cx = 0.5,
        float $cy = 0.5,
        float $radius = 0.5,
        float $innerRadius = 0.0,
        GradientSpread $spread = GradientSpread::Pad,
    ): self {
        return new self($stops, $cx, $cy, $radius, $innerRadius, $focalX, $focalY, $spread);
    }

    public function shadingType(): int
    {
        return 3;
    }

    public function coords(\Closure $place, float $boxWidthPt, float $boxHeightPt): array
    {
        $reference = max($boxWidthPt, $boxHeightPt);
        [$fx, $fy] = $place($this->focalX * $boxWidthPt, $this->focalY * $boxHeightPt);
        [$cx, $cy] = $place($this->cx * $boxWidthPt, $this->cy * $boxHeightPt);

        return [$fx, $fy, $this->innerRadius * $reference, $cx, $cy, $this->radius * $reference];
    }
}
