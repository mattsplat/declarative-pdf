<?php

declare(strict_types=1);

namespace Pdf\Layout;

use Pdf\Style\ColumnWidth;

/**
 * Automatic table column sizing — a deterministic take on CSS "automatic table
 * layout".
 *
 * Given the available width, the per-column {@see ColumnWidth} spec and the
 * per-column (min, max) intrinsic widths, produce concrete column widths:
 *
 *  1. Fixed columns take their width.
 *  2. If every flexible column can have its max within the remaining space,
 *     it does, and the leftover goes to `fraction` columns by weight (or
 *     equally to `auto` columns).
 *  3. Otherwise, if every flexible column can have at least its min, widths
 *     interpolate between min and max across the flex range.
 *  4. Otherwise columns shrink proportionally to their min (content overflows).
 *
 * Column widths always sum to `max(available, Σ min)`.
 */
final class TableLayout
{
    private const EPSILON = 1e-6;

    /**
     * @param list<ColumnWidth>                    $specs
     * @param list<array{min: float, max: float}>  $content aggregated per column, padding included
     * @return list<float>
     */
    public static function resolve(float $availableWidthPt, array $specs, array $content): array
    {
        $n = count($specs);

        /** @var list<float> $min */
        $min = [];
        /** @var list<float> $max */
        $max = [];
        for ($i = 0; $i < $n; $i++) {
            if ($specs[$i]->isFixed()) {
                $min[$i] = $specs[$i]->value;
                $max[$i] = $specs[$i]->value;
                continue;
            }
            $cMin = $specs[$i]->clamp($content[$i]['min']);
            $cMax = $specs[$i]->clamp(max($content[$i]['max'], $content[$i]['min']));
            $min[$i] = $cMin;
            $max[$i] = max($cMax, $cMin);
        }

        $sumMin = array_sum($min);
        $target = max($availableWidthPt, $sumMin);

        /** @var list<int> $flex */
        $flex = [];
        $fixedTotal = 0.0;
        for ($i = 0; $i < $n; $i++) {
            if ($specs[$i]->isFixed()) {
                $fixedTotal += $specs[$i]->value;
            } else {
                $flex[] = $i;
            }
        }

        $widths = $min;
        foreach ($specs as $i => $spec) {
            if ($spec->isFixed()) {
                $widths[$i] = $spec->value;
            }
        }

        if ($flex === []) {
            // All columns fixed: grow them proportionally to fill spare width
            // (never shrink below the requested widths).
            $sum = array_sum($widths);
            if ($sum > self::EPSILON && $target - $sum > self::EPSILON) {
                $factor = $target / $sum;
                foreach ($widths as $i => $w) {
                    $widths[$i] = $w * $factor;
                }
            }

            return array_map(static fn (float $w) => max(0.0, $w), $widths);
        }

        $remaining = $target - $fixedTotal;
        $flexMin = 0.0;
        $flexMax = 0.0;
        foreach ($flex as $i) {
            $flexMin += $min[$i];
            $flexMax += $max[$i];
        }

        if ($flexMax <= $remaining + self::EPSILON) {
            foreach ($flex as $i) {
                $widths[$i] = $max[$i];
            }
            $leftover = $remaining - $flexMax;
            if ($leftover > self::EPSILON) {
                $widths = self::distributeLeftover($widths, $flex, $specs, $leftover);
            }
        } elseif ($flexMin <= $remaining + self::EPSILON) {
            $span = $flexMax - $flexMin;
            $slack = $remaining - $flexMin;
            foreach ($flex as $i) {
                $range = $max[$i] - $min[$i];
                $widths[$i] = $min[$i] + ($span > self::EPSILON
                    ? $slack * $range / $span
                    : $slack / count($flex));
            }
        } else {
            $scale = $flexMin > self::EPSILON ? $remaining / $flexMin : 0.0;
            foreach ($flex as $i) {
                $widths[$i] = $min[$i] * $scale;
            }
        }

        foreach ($flex as $i) {
            $widths[$i] = $specs[$i]->clamp($widths[$i]);
        }

        // Exact sum: distribute any residual only among flexible columns that
        // have room to move in that direction, re-clamping each pass so no
        // clamp is ever pushed past.
        for ($pass = 0; $pass < 4; $pass++) {
            $residual = $target - array_sum($widths);
            if (abs($residual) <= self::EPSILON) {
                break;
            }
            $growing = $residual > 0.0;
            $adjustable = array_values(array_filter($flex, static function (int $i) use ($specs, $widths, $growing): bool {
                $limit = $growing ? $specs[$i]->maxPt : $specs[$i]->minPt;

                return $limit === null || abs($widths[$i] - $limit) > self::EPSILON;
            }));
            if ($adjustable === []) {
                $adjustable = $flex;
            }
            $per = $residual / count($adjustable);
            foreach ($adjustable as $i) {
                $widths[$i] = $specs[$i]->clamp($widths[$i] + $per);
            }
        }

        return array_map(static fn (float $w) => max(0.0, $w), $widths);
    }

    /**
     * @param list<float>       $widths
     * @param list<int>         $flex
     * @param list<ColumnWidth> $specs
     * @return list<float>
     */
    private static function distributeLeftover(array $widths, array $flex, array $specs, float $leftover): array
    {
        $fractions = array_values(array_filter($flex, static fn (int $i) => $specs[$i]->isFraction()));

        if ($fractions !== []) {
            $totalWeight = 0.0;
            foreach ($fractions as $i) {
                $totalWeight += $specs[$i]->value;
            }
            foreach ($fractions as $i) {
                $widths[$i] += $leftover * $specs[$i]->value / $totalWeight;
            }

            return $widths;
        }

        $per = $leftover / count($flex);
        foreach ($flex as $i) {
            $widths[$i] += $per;
        }

        return $widths;
    }
}
