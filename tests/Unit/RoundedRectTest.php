<?php

declare(strict_types=1);

namespace Pdf\Tests\Unit;

use Pdf\Geometry\PathOp;
use Pdf\Geometry\RoundedRect;
use PHPUnit\Framework\TestCase;

final class RoundedRectTest extends TestCase
{
    public function test_zero_radii_degenerate_to_a_plain_rectangle(): void
    {
        $commands = RoundedRect::commands(100.0, 40.0, 0.0, 0.0, 0.0, 0.0);

        self::assertSame(
            [PathOp::MoveTo, PathOp::LineTo, PathOp::LineTo, PathOp::LineTo, PathOp::LineTo, PathOp::Close],
            array_map(static fn ($c) => $c->op, $commands),
        );
    }

    public function test_a_uniform_radius_rounds_all_four_corners(): void
    {
        $commands = RoundedRect::commands(100.0, 40.0, 6.0, 6.0, 6.0, 6.0);

        self::assertSame(
            [
                PathOp::MoveTo,
                PathOp::LineTo, PathOp::CurveTo,
                PathOp::LineTo, PathOp::CurveTo,
                PathOp::LineTo, PathOp::CurveTo,
                PathOp::LineTo, PathOp::CurveTo,
                PathOp::Close,
            ],
            array_map(static fn ($c) => $c->op, $commands),
        );
    }

    public function test_only_the_requested_corners_round(): void
    {
        // Top corners square, bottom corners rounded.
        $commands = RoundedRect::commands(100.0, 40.0, 0.0, 0.0, 6.0, 6.0);

        self::assertSame(
            [
                PathOp::MoveTo,
                PathOp::LineTo, PathOp::LineTo, PathOp::CurveTo,
                PathOp::LineTo, PathOp::CurveTo,
                PathOp::LineTo,
                PathOp::Close,
            ],
            array_map(static fn ($c) => $c->op, $commands),
        );
    }

    public function test_a_radius_larger_than_half_the_box_is_clamped(): void
    {
        // A 40x40 box with a "radius" of 1000 should still just be a pill/circle,
        // not an invalid shape with overlapping curves.
        $commands = RoundedRect::commands(40.0, 40.0, 1000.0, 1000.0, 1000.0, 1000.0);
        $start = $commands[0]->points[0];

        self::assertSame(20.0, $start->x);
        self::assertSame(0.0, $start->y);
    }

    public function test_the_corner_control_points_use_the_kappa_approximation(): void
    {
        $commands = RoundedRect::commands(100.0, 100.0, 10.0, 10.0, 10.0, 10.0);

        // The top-right corner's curve starts at (90, 0) and its first control
        // point is kappa * radius to the right of that, same offset as a quarter
        // of Path::ellipse()'s full-circle approximation.
        $curve = $commands[2];
        self::assertSame(PathOp::CurveTo, $curve->op);
        self::assertEqualsWithDelta(90.0 + 10.0 * 0.5523, $curve->points[0]->x, 1e-9);
        self::assertEqualsWithDelta(0.0, $curve->points[0]->y, 1e-9);
    }

    public function test_each_corner_can_have_its_own_radius(): void
    {
        // Top-left and bottom-right rounded, top-right and bottom-left square.
        $commands = RoundedRect::commands(100.0, 40.0, 12.0, 0.0, 12.0, 0.0);

        self::assertSame(
            [
                PathOp::MoveTo,
                PathOp::LineTo,
                PathOp::LineTo, PathOp::CurveTo,
                PathOp::LineTo,
                PathOp::LineTo, PathOp::CurveTo,
                PathOp::Close,
            ],
            array_map(static fn ($c) => $c->op, $commands),
        );
    }
}
