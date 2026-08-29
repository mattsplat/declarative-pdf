<?php

declare(strict_types=1);

namespace Pdf\Tests\Unit;

use Pdf\Chart\Plot;
use Pdf\Chart\Scale;
use PHPUnit\Framework\TestCase;

final class PlotTest extends TestCase
{
    private function plot(): Plot
    {
        // 200x100 plot at (10, 20); value axis 0..80.
        return new Plot(10.0, 20.0, 200.0, 100.0, Scale::nice(0.0, 80.0));
    }

    public function test_value_axis_runs_bottom_up(): void
    {
        $plot = $this->plot();

        self::assertSame(120.0, $plot->valueY(0.0));   // bottom edge (20 + 100)
        self::assertSame(20.0, $plot->valueY(80.0));    // top edge
        self::assertSame(70.0, $plot->valueY(40.0));    // midpoint
    }

    public function test_slots_are_evenly_spaced_and_centred(): void
    {
        $plot = $this->plot();

        self::assertSame(35.0, $plot->slotCentre(0, 4));   // 10 + 0.125 * 200
        self::assertSame(185.0, $plot->slotCentre(3, 4));  // 10 + 0.875 * 200
    }

    public function test_bars_partition_a_slot_between_series_without_overlap(): void
    {
        $plot = $this->plot();

        [$x0, $w0] = $plot->bar(0, 4, 0, 2);
        [$x1, $w1] = $plot->bar(0, 4, 1, 2);

        self::assertSame($w0, $w1);
        self::assertEqualsWithDelta($x0 + $w0, $x1, 1e-9);

        // Two bars, 30% inter-group gap: each is 0.35 of the 50pt slot.
        self::assertEqualsWithDelta(17.5, $w0, 1e-9);

        // The group is centred in its slot.
        $slotCentre = $plot->slotCentre(0, 4);
        self::assertEqualsWithDelta($slotCentre, $x0 + $w0, 1e-9);
    }

    public function test_single_series_bar_is_centred_in_its_slot(): void
    {
        $plot = $this->plot();
        [$x, $w] = $plot->bar(2, 4, 0, 1);

        self::assertEqualsWithDelta($plot->slotCentre(2, 4), $x + $w / 2, 1e-9);
    }

    public function test_line_walks_one_point_per_value(): void
    {
        $points = $this->plot()->line([0.0, 40.0, 80.0]);

        self::assertCount(3, $points);
        self::assertEqualsWithDelta(120.0, $points[0]->y, 1e-9);
        self::assertEqualsWithDelta(70.0, $points[1]->y, 1e-9);
        self::assertEqualsWithDelta(20.0, $points[2]->y, 1e-9);
    }

    public function test_pie_angles_sum_to_a_full_turn_and_start_at_twelve_o_clock(): void
    {
        $angles = Plot::pieAngles([1.0, 1.0, 2.0]);

        self::assertCount(3, $angles);
        self::assertSame(-90.0, $angles[0][0]);
        self::assertEqualsWithDelta(270.0, $angles[2][1], 1e-9);                 // -90 + 360
        self::assertEqualsWithDelta(180.0, $angles[2][1] - $angles[2][0], 1e-9); // 2 of 4 -> half the pie
        self::assertEqualsWithDelta($angles[0][1], $angles[1][0], 1e-9);         // slices are contiguous
    }

    public function test_pie_angles_use_magnitude_so_a_zero_total_is_safe(): void
    {
        $angles = Plot::pieAngles([0.0, 0.0]);

        self::assertSame([-90.0, -90.0], [$angles[0][0], $angles[0][1]]);
    }
}
