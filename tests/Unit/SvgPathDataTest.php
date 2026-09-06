<?php

declare(strict_types=1);

namespace Pdf\Tests\Unit;

use Pdf\Exception\SvgException;
use Pdf\Geometry\PathCommand;
use Pdf\Geometry\PathOp;
use Pdf\Svg\PathData;
use PHPUnit\Framework\TestCase;

final class SvgPathDataTest extends TestCase
{
    /** @return list<PathOp> */
    private function ops(string $d): array
    {
        return array_map(static fn (PathCommand $c): PathOp => $c->op, PathData::parse($d));
    }

    /** Every operand of every command, flattened, for terse coordinate assertions. */
    private function coordinates(string $d): string
    {
        $parts = [];
        foreach (PathData::parse($d) as $command) {
            foreach ($command->points as $point) {
                $parts[] = sprintf('%.3F,%.3F', $point->x, $point->y);
            }
        }

        return implode(' ', $parts);
    }

    public function test_absolute_moveto_and_lineto(): void
    {
        self::assertSame([PathOp::MoveTo, PathOp::LineTo], $this->ops('M 10 20 L 30 40'));
        self::assertSame('10.000,20.000 30.000,40.000', $this->coordinates('M 10 20 L 30 40'));
    }

    public function test_relative_commands_accumulate_from_the_current_point(): void
    {
        self::assertSame('10.000,10.000 15.000,15.000 20.000,25.000', $this->coordinates('m 10 10 l 5 5 l 5 10'));
    }

    public function test_a_bare_coordinate_pair_repeats_the_previous_command(): void
    {
        self::assertSame([PathOp::LineTo, PathOp::LineTo, PathOp::LineTo], $this->ops('L 1 1 2 2 3 3'));
    }

    public function test_a_repeated_moveto_becomes_a_lineto(): void
    {
        // "M 0 0 1 1" is a moveto followed by an implicit lineto, not two movetos.
        self::assertSame([PathOp::MoveTo, PathOp::LineTo], $this->ops('M 0 0 1 1'));
    }

    public function test_horizontal_and_vertical_shorthands_become_lines(): void
    {
        self::assertSame('0.000,0.000 50.000,0.000 50.000,25.000', $this->coordinates('M 0 0 H 50 V 25'));
    }

    public function test_relative_vertical_is_relative_to_the_current_y(): void
    {
        self::assertSame('0.000,10.000 0.000,25.000', $this->coordinates('M 0 10 v 15'));
    }

    public function test_smooth_cubic_reflects_the_previous_control_point(): void
    {
        // The second curve's first control mirrors (8,0) about the join at (10,0).
        self::assertSame(
            '0.000,0.000 2.000,0.000 8.000,0.000 10.000,0.000 12.000,0.000 18.000,0.000 20.000,0.000',
            $this->coordinates('M 0 0 C 2 0 8 0 10 0 S 18 0 20 0'),
        );
    }

    public function test_smooth_cubic_without_a_preceding_curve_uses_the_current_point(): void
    {
        self::assertSame('5.000,5.000 5.000,5.000 8.000,0.000 10.000,0.000', $this->coordinates('M 5 5 S 8 0 10 0'));
    }

    public function test_quadratic_is_elevated_to_an_equivalent_cubic(): void
    {
        // Controls sit two thirds of the way from each endpoint to (10,0).
        self::assertSame(
            '0.000,0.000 6.667,0.000 10.000,3.333 10.000,10.000',
            $this->coordinates('M 0 0 Q 10 0 10 10'),
        );
    }

    public function test_smooth_quadratic_reflects_the_previous_quadratic_control(): void
    {
        $commands = PathData::parse('M 0 0 Q 5 5 10 0 T 20 0');
        self::assertCount(3, $commands);
        // The reflected control is (15,-5); elevated, the first cubic control
        // is two thirds of the way there from (10,0).
        self::assertSame(13.333, round($commands[2]->points[0]->x, 3));
        self::assertSame(-3.333, round($commands[2]->points[0]->y, 3));
    }

    public function test_closepath_returns_the_current_point_to_the_subpath_start(): void
    {
        // Close carries no operands, so the trailing relative lineto is what
        // proves Z restored the current point to (10,10).
        self::assertSame([PathOp::MoveTo, PathOp::LineTo, PathOp::Close, PathOp::LineTo], $this->ops('M 10 10 L 20 10 Z l 5 0'));
        self::assertSame(
            '10.000,10.000 20.000,10.000 15.000,10.000',
            $this->coordinates('M 10 10 L 20 10 Z l 5 0'),
        );
    }

    public function test_a_quarter_arc_becomes_one_cubic_ending_at_the_stated_point(): void
    {
        $commands = PathData::parse('M 10 0 A 10 10 0 0 1 0 10');

        self::assertSame([PathOp::MoveTo, PathOp::CurveTo], array_map(static fn ($c) => $c->op, $commands));
        $end = $commands[1]->points[2];
        self::assertSame(0.0, round($end->x, 6));
        self::assertSame(10.0, round($end->y, 6));
    }

    public function test_a_full_circle_arc_pair_is_split_into_quarter_segments(): void
    {
        // Each semicircle needs two cubics to stay within tolerance.
        self::assertSame(
            [PathOp::MoveTo, PathOp::CurveTo, PathOp::CurveTo, PathOp::CurveTo, PathOp::CurveTo],
            $this->ops('M 10 0 A 10 10 0 1 1 -10 0 A 10 10 0 1 1 10 0'),
        );
    }

    public function test_arc_flags_need_no_separator(): void
    {
        // svgo emits this form: rx=1 ry=1 rot=0 large=0 sweep=1 x=1 y=1.
        $packed = PathData::parse('M0 0a1 1 0 011 1');
        $spaced = PathData::parse('M0 0a1 1 0 0 1 1 1');

        self::assertEquals($spaced, $packed);
    }

    public function test_a_zero_radius_arc_degenerates_to_a_line(): void
    {
        self::assertSame([PathOp::MoveTo, PathOp::LineTo], $this->ops('M 0 0 A 0 0 0 0 1 10 10'));
    }

    public function test_a_zero_length_arc_is_dropped(): void
    {
        self::assertSame([PathOp::MoveTo], $this->ops('M 5 5 A 10 10 0 0 1 5 5'));
    }

    public function test_scientific_notation_and_omitted_separators(): void
    {
        // "-.5.5" is two numbers; "1e1" is ten.
        self::assertSame('-0.500,0.500 10.000,10.000', $this->coordinates('M-.5.5L1e1 1e1'));
    }

    public function test_path_data_must_begin_with_a_command(): void
    {
        $this->expectException(SvgException::class);
        PathData::parse('10 10 L 20 20');
    }

    public function test_a_coordinate_after_closepath_is_rejected(): void
    {
        // Z takes no arguments, so repeating it would never consume the number.
        $this->expectException(SvgException::class);
        PathData::parse('M 0 0 L 1 1 Z 5 5');
    }

    public function test_an_unknown_command_letter_is_rejected(): void
    {
        $this->expectException(SvgException::class);
        PathData::parse('M 0 0 X 5 5');
    }

    public function test_a_truncated_command_is_rejected(): void
    {
        $this->expectException(SvgException::class);
        PathData::parse('M 0 0 L 5');
    }
}
