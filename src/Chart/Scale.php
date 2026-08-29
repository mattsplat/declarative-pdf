<?php

declare(strict_types=1);

namespace Pdf\Chart;

/**
 * A value axis rounded to human-friendly bounds and a "nice" tick step
 * (…, 1, 2, 5, 10, 20, 50, …), by the algorithm from Heckbert's *Graphics
 * Gems* "Nice numbers for graph labels".
 *
 * Pure and deterministic: the same `(min, max, maxTicks)` always yields the
 * same bounds, step and tick list, which is what keeps a rendered chart
 * byte-stable.
 */
final readonly class Scale
{
    private function __construct(
        public float $min,
        public float $max,
        public float $step,
    ) {
    }

    public static function nice(float $dataMin, float $dataMax, int $maxTicks = 5): self
    {
        if ($maxTicks < 2) {
            $maxTicks = 2;
        }
        if ($dataMax < $dataMin) {
            [$dataMin, $dataMax] = [$dataMax, $dataMin];
        }
        // A flat series still needs a non-zero span to divide by.
        if ($dataMax - $dataMin < 1e-9) {
            $dataMax = $dataMin + 1.0;
        }

        $range = self::niceNum($dataMax - $dataMin, false);
        $step = self::niceNum($range / ($maxTicks - 1), true);
        $min = floor($dataMin / $step) * $step;
        $max = ceil($dataMax / $step) * $step;

        return new self($min, $max, $step);
    }

    public function span(): float
    {
        return $this->max - $this->min;
    }

    /** @return list<float> every tick from min to max inclusive. */
    public function ticks(): array
    {
        $ticks = [];
        $count = (int) round($this->span() / $this->step);
        for ($i = 0; $i <= $count; $i++) {
            $ticks[] = $this->min + $i * $this->step;
        }

        return $ticks;
    }

    /** Fraction (0 at min, 1 at max) of where `$value` falls on the axis. */
    public function fraction(float $value): float
    {
        return ($value - $this->min) / $this->span();
    }

    private static function niceNum(float $value, bool $round): float
    {
        $exponent = (int) floor(log10($value));
        $fraction = $value / (10 ** $exponent);

        $niceFraction = $round
            ? match (true) {
                $fraction < 1.5 => 1.0,
                $fraction < 3.0 => 2.0,
                $fraction < 7.0 => 5.0,
                default => 10.0,
            }
            : match (true) {
                $fraction <= 1.0 => 1.0,
                $fraction <= 2.0 => 2.0,
                $fraction <= 5.0 => 5.0,
                default => 10.0,
            };

        return $niceFraction * (10 ** $exponent);
    }
}
