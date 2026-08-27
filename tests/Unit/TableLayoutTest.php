<?php

declare(strict_types=1);

namespace Pdf\Tests\Unit;

use Pdf\Layout\TableLayout;
use Pdf\Style\ColumnWidth;
use PHPUnit\Framework\TestCase;

final class TableLayoutTest extends TestCase
{
    /**
     * @param list<float> $widths
     */
    private static function assertSumsTo(float $expected, array $widths): void
    {
        self::assertEqualsWithDelta($expected, array_sum($widths), 1e-6, 'column widths must sum exactly to the target');
    }

    public function test_fixed_columns_take_their_width_and_flex_fills_the_rest(): void
    {
        $widths = TableLayout::resolve(300.0, [
            ColumnWidth::fixed(100.0),
            ColumnWidth::auto(),
        ], [
            ['min' => 20.0, 'max' => 50.0],
            ['min' => 30.0, 'max' => 400.0],
        ]);

        self::assertSame(100.0, $widths[0]);
        self::assertEqualsWithDelta(200.0, $widths[1], 1e-6);
        self::assertSumsTo(300.0, $widths);
    }

    public function test_regime_one_everything_fits_at_max_and_leftover_spreads(): void
    {
        // sum(max) = 120 < 300 available -> each gets max 40, +180 spread equally = 100 each.
        $widths = TableLayout::resolve(300.0, [
            ColumnWidth::auto(),
            ColumnWidth::auto(),
            ColumnWidth::auto(),
        ], [
            ['min' => 10.0, 'max' => 40.0],
            ['min' => 10.0, 'max' => 40.0],
            ['min' => 10.0, 'max' => 40.0],
        ]);

        foreach ($widths as $w) {
            self::assertEqualsWithDelta(100.0, $w, 1e-6);
        }
        self::assertSumsTo(300.0, $widths);
    }

    public function test_regime_one_leftover_goes_to_fraction_columns_by_weight(): void
    {
        $widths = TableLayout::resolve(300.0, [
            ColumnWidth::auto(),
            ColumnWidth::fraction(1.0),
            ColumnWidth::fraction(3.0),
        ], [
            ['min' => 10.0, 'max' => 60.0],
            ['min' => 10.0, 'max' => 60.0],
            ['min' => 10.0, 'max' => 60.0],
        ]);

        // 300 - 180(maxes) = 120 leftover; split 1:3 between cols 1 and 2.
        self::assertEqualsWithDelta(60.0, $widths[0], 1e-6);
        self::assertEqualsWithDelta(60.0 + 30.0, $widths[1], 1e-6);
        self::assertEqualsWithDelta(60.0 + 90.0, $widths[2], 1e-6);
        self::assertSumsTo(300.0, $widths);
    }

    public function test_regime_two_interpolates_between_min_and_max_by_flex_range(): void
    {
        // sum(min)=40 <= 100 <= sum(max)=340 -> interpolate.
        $widths = TableLayout::resolve(100.0, [
            ColumnWidth::auto(),
            ColumnWidth::auto(),
        ], [
            ['min' => 20.0, 'max' => 20.0],   // no range -> stays at 20
            ['min' => 20.0, 'max' => 320.0],  // absorbs all the slack
        ]);

        self::assertEqualsWithDelta(20.0, $widths[0], 1e-6);
        self::assertEqualsWithDelta(80.0, $widths[1], 1e-6);
        self::assertSumsTo(100.0, $widths);
    }

    public function test_regime_three_overflows_to_sum_of_min_when_min_does_not_fit(): void
    {
        $widths = TableLayout::resolve(50.0, [
            ColumnWidth::auto(),
            ColumnWidth::auto(),
        ], [
            ['min' => 60.0, 'max' => 60.0],
            ['min' => 40.0, 'max' => 40.0],
        ]);

        // Available 50 < sum(min) 100 -> widths shrink proportionally but sum to 100.
        self::assertSumsTo(100.0, $widths);
        self::assertGreaterThan($widths[1], $widths[0]);
    }

    public function test_max_clamp_is_respected_and_sum_is_still_exact(): void
    {
        $widths = TableLayout::resolve(300.0, [
            ColumnWidth::auto(maxPt: 80.0),
            ColumnWidth::auto(),
        ], [
            ['min' => 10.0, 'max' => 500.0],
            ['min' => 10.0, 'max' => 500.0],
        ]);

        self::assertLessThanOrEqual(80.0 + 1e-6, $widths[0]);
        self::assertSumsTo(300.0, $widths);
    }

    public function test_is_deterministic(): void
    {
        $specs = [ColumnWidth::auto(), ColumnWidth::fraction(2.0), ColumnWidth::fixed(70.0)];
        $content = [
            ['min' => 15.0, 'max' => 90.0],
            ['min' => 25.0, 'max' => 130.0],
            ['min' => 0.0, 'max' => 0.0],
        ];

        self::assertSame(
            TableLayout::resolve(400.0, $specs, $content),
            TableLayout::resolve(400.0, $specs, $content),
        );
    }
}
