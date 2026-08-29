<?php

declare(strict_types=1);

namespace Pdf\Tests\Unit;

use Pdf\Chart\Scale;
use PHPUnit\Framework\TestCase;

final class ScaleTest extends TestCase
{
    public function test_rounds_bounds_out_to_a_nice_step(): void
    {
        $scale = Scale::nice(0.0, 76.0);

        self::assertSame(0.0, $scale->min);
        self::assertSame(80.0, $scale->max);
        self::assertSame(20.0, $scale->step);
        self::assertSame([0.0, 20.0, 40.0, 60.0, 80.0], $scale->ticks());
    }

    public function test_step_is_drawn_from_the_one_two_five_ladder(): void
    {
        self::assertSame(2.0, Scale::nice(0.0, 9.0)->step);
        self::assertSame(20.0, Scale::nice(0.0, 90.0)->step);
        self::assertSame(1.0, Scale::nice(0.0, 2.3)->step);
        self::assertSame(2000.0, Scale::nice(0.0, 9000.0)->step);
    }

    public function test_a_non_zero_minimum_is_floored_to_the_step(): void
    {
        $scale = Scale::nice(120.0, 205.0);

        self::assertSame(120.0, $scale->min);
        self::assertSame(220.0, $scale->max);
        self::assertSame(20.0, $scale->step);
    }

    public function test_negative_data_straddles_zero(): void
    {
        $scale = Scale::nice(-30.0, 40.0);

        self::assertLessThanOrEqual(-30.0, $scale->min);
        self::assertGreaterThanOrEqual(40.0, $scale->max);
        self::assertContains(0.0, $scale->ticks());
    }

    public function test_a_flat_series_still_produces_a_usable_span(): void
    {
        $scale = Scale::nice(5.0, 5.0);

        self::assertGreaterThan($scale->min, $scale->max);
        self::assertGreaterThan(0.0, $scale->step);
    }

    public function test_fraction_maps_min_and_max_to_zero_and_one(): void
    {
        $scale = Scale::nice(0.0, 80.0);

        self::assertSame(0.0, $scale->fraction(0.0));
        self::assertSame(1.0, $scale->fraction(80.0));
        self::assertSame(0.5, $scale->fraction(40.0));
    }

    public function test_target_tick_count_is_respected_loosely(): void
    {
        $scale = Scale::nice(0.0, 100.0, 11);

        self::assertGreaterThanOrEqual(8, count($scale->ticks()));
        self::assertLessThanOrEqual(14, count($scale->ticks()));
    }
}
