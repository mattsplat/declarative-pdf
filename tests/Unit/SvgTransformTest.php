<?php

declare(strict_types=1);

namespace Pdf\Tests\Unit;

use Pdf\Exception\SvgException;
use Pdf\Geometry\Point;
use Pdf\Svg\Matrix;
use PHPUnit\Framework\TestCase;

final class SvgTransformTest extends TestCase
{
    private function applied(string $transform, float $x, float $y): string
    {
        $point = Matrix::parse($transform)->apply(new Point($x, $y));

        return sprintf('%.3F,%.3F', $point->x, $point->y);
    }

    public function test_an_empty_transform_is_the_identity(): void
    {
        self::assertTrue(Matrix::parse('')->isIdentity());
        self::assertSame('3.000,4.000', $this->applied('', 3.0, 4.0));
    }

    public function test_translate_with_one_argument_leaves_y_alone(): void
    {
        self::assertSame('15.000,4.000', $this->applied('translate(10)', 5.0, 4.0));
    }

    public function test_translate_and_scale(): void
    {
        self::assertSame('12.000,14.000', $this->applied('translate(10,10)', 2.0, 4.0));
        self::assertSame('4.000,12.000', $this->applied('scale(2,3)', 2.0, 4.0));
        self::assertSame('4.000,8.000', $this->applied('scale(2)', 2.0, 4.0));
    }

    public function test_rotation_turns_towards_positive_y(): void
    {
        // SVG's y axis points down, so a positive rotation is clockwise on screen.
        self::assertSame('0.000,1.000', $this->applied('rotate(90)', 1.0, 0.0));
    }

    public function test_rotation_about_a_centre(): void
    {
        // Half a turn about (5,5) maps (6,5) onto (4,5).
        self::assertSame('4.000,5.000', $this->applied('rotate(180, 5, 5)', 6.0, 5.0));
    }

    public function test_skew_shears_along_one_axis(): void
    {
        self::assertSame('11.000,10.000', $this->applied('skewX(45)', 1.0, 10.0));
        self::assertSame('10.000,11.000', $this->applied('skewY(45)', 10.0, 1.0));
    }

    public function test_matrix_takes_the_six_components_verbatim(): void
    {
        self::assertSame('7.000,10.000', $this->applied('matrix(1,2,3,4,0,0)', 1.0, 2.0));
    }

    public function test_a_transform_list_applies_right_to_left(): void
    {
        // The scale acts on the geometry first, then the translation moves it.
        self::assertSame('12.000,2.000', $this->applied('translate(10,0) scale(2)', 1.0, 1.0));
        self::assertSame('22.000,2.000', $this->applied('scale(2) translate(10,0)', 1.0, 1.0));
    }

    public function test_separators_between_functions_are_flexible(): void
    {
        self::assertSame(
            $this->applied('translate(10,0) scale(2)', 1.0, 1.0),
            $this->applied('translate(10 0),scale(2)', 1.0, 1.0),
        );
    }

    public function test_mean_scale_is_the_geometric_mean_of_the_axis_scales(): void
    {
        self::assertSame(2.0, Matrix::parse('scale(2)')->meanScale());
        self::assertSame(2.0, Matrix::parse('scale(1,4)')->meanScale());
        // A rotation preserves length, so it must not change the stroke width.
        self::assertSame(1.0, round(Matrix::parse('rotate(37)')->meanScale(), 12));
    }

    public function test_an_unknown_transform_function_is_rejected(): void
    {
        $this->expectException(SvgException::class);
        Matrix::parse('warp(3)');
    }

    public function test_a_wrong_argument_count_is_rejected(): void
    {
        $this->expectException(SvgException::class);
        Matrix::parse('rotate(1, 2)');
    }
}
