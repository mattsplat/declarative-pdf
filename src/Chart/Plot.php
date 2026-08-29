<?php

declare(strict_types=1);

namespace Pdf\Chart;

use Pdf\Geometry\Point;

/**
 * Maps chart data onto a rectangle of points, top-left origin, y down — the
 * same coordinate space every box renders in. Pure geometry: no fonts, no
 * canvas, no colour, so the bar / line / slice maths is unit-testable on its
 * own.
 */
final readonly class Plot
{
    public function __construct(
        public float $leftPt,
        public float $topPt,
        public float $widthPt,
        public float $heightPt,
        public Scale $scale,
    ) {
    }

    /** y-from-top of a data value on the value axis. */
    public function valueY(float $value): float
    {
        return $this->topPt + $this->heightPt - $this->scale->fraction($value) * $this->heightPt;
    }

    /** x of the centre of category slot `$index` of `$count` evenly spaced slots. */
    public function slotCentre(int $index, int $count): float
    {
        return $this->leftPt + ($index + 0.5) / max(1, $count) * $this->widthPt;
    }

    /**
     * `[x, width]` of one bar: series `$series` of `$seriesCount` within
     * category slot `$category` of `$categoryCount`. `$groupGap` is the share
     * of a slot left empty between neighbouring categories (0…1).
     *
     * @return array{0: float, 1: float}
     */
    public function bar(
        int $category,
        int $categoryCount,
        int $series,
        int $seriesCount,
        float $groupGap = 0.3,
    ): array {
        $slot = $this->widthPt / max(1, $categoryCount);
        $groupWidth = $slot * (1.0 - $groupGap);
        $barWidth = $groupWidth / max(1, $seriesCount);
        $groupLeft = $this->leftPt + $category * $slot + ($slot - $groupWidth) / 2;

        return [$groupLeft + $series * $barWidth, $barWidth];
    }

    /**
     * The polyline through `$values`, one point per evenly spaced slot.
     *
     * @param list<float> $values
     * @return list<Point>
     */
    public function line(array $values): array
    {
        $count = count($values);
        $points = [];
        foreach ($values as $index => $value) {
            $points[] = new Point($this->slotCentre($index, $count), $this->valueY($value));
        }

        return $points;
    }

    /**
     * Start/end angles in degrees for each slice, clockwise from 12 o'clock,
     * proportional to `|value|`. Independent of any rectangle.
     *
     * @param list<float> $values
     * @return list<array{0: float, 1: float}>
     */
    public static function pieAngles(array $values, float $startDeg = -90.0): array
    {
        $total = 0.0;
        foreach ($values as $value) {
            $total += abs($value);
        }

        $angles = [];
        $cursor = $startDeg;
        foreach ($values as $value) {
            $sweep = $total > 0.0 ? abs($value) / $total * 360.0 : 0.0;
            $angles[] = [$cursor, $cursor + $sweep];
            $cursor += $sweep;
        }

        return $angles;
    }
}
