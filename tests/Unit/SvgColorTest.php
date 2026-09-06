<?php

declare(strict_types=1);

namespace Pdf\Tests\Unit;

use Pdf\Color\Color;
use Pdf\Exception\SvgException;
use Pdf\Svg\SvgColor;
use PHPUnit\Framework\TestCase;

final class SvgColorTest extends TestCase
{
    public function test_hex_forms(): void
    {
        self::assertTrue(Color::rgb(255, 0, 0)->equals(SvgColor::parse('#f00') ?? Color::black()));
        self::assertTrue(Color::rgb(18, 52, 86)->equals(SvgColor::parse('#123456') ?? Color::black()));
    }

    public function test_named_colours(): void
    {
        self::assertTrue(Color::rgb(255, 165, 0)->equals(SvgColor::parse('orange') ?? Color::black()));
        self::assertTrue(Color::rgb(102, 51, 153)->equals(SvgColor::parse('RebeccaPurple') ?? Color::black()));
    }

    public function test_rgb_function_in_numbers_and_percentages(): void
    {
        self::assertTrue(Color::rgb(0, 128, 255)->equals(SvgColor::parse('rgb(0, 128, 255)') ?? Color::black()));
        self::assertTrue(Color::rgb(255, 0, 0)->equals(SvgColor::parse('rgb(100%, 0%, 0%)') ?? Color::black()));
    }

    public function test_none_and_transparent_paint_nothing(): void
    {
        self::assertNull(SvgColor::parse('none'));
        self::assertNull(SvgColor::parse('transparent'));
    }

    public function test_current_colour_resolves_to_the_inherited_colour(): void
    {
        $inherited = Color::rgb(1, 2, 3);
        self::assertTrue($inherited->equals(SvgColor::parse('currentColor', $inherited) ?? Color::black()));
        self::assertTrue(Color::black()->equals(SvgColor::parse('currentColor') ?? Color::white()));
    }

    public function test_alpha_is_read_separately_from_the_colour(): void
    {
        self::assertSame(1.0, SvgColor::alpha('#f00'));
        self::assertSame(1.0, SvgColor::alpha('rgb(1,2,3)'));
        self::assertSame(1.0, SvgColor::alpha('red'));
        self::assertSame(0.5, round(SvgColor::alpha('#ff000080'), 2));
        self::assertSame(0.5, SvgColor::alpha('rgba(255, 0, 0, 0.5)'));
        self::assertSame(0.5, SvgColor::alpha('rgb(255 0 0 / 50%)'));
    }

    public function test_a_colour_carrying_alpha_still_yields_its_rgb_half(): void
    {
        self::assertTrue(Color::rgb(255, 0, 0)->equals(SvgColor::parse('#ff000080') ?? Color::black()));
        self::assertTrue(Color::rgb(255, 0, 0)->equals(SvgColor::parse('rgba(255,0,0,0.5)') ?? Color::black()));
    }

    public function test_opacity_accepts_numbers_and_percentages_and_clamps(): void
    {
        self::assertSame(0.25, SvgColor::opacity('0.25'));
        self::assertSame(0.25, SvgColor::opacity('25%'));
        self::assertSame(1.0, SvgColor::opacity('4'));
        self::assertSame(0.0, SvgColor::opacity('-1'));
    }

    public function test_an_unsupported_colour_is_rejected(): void
    {
        $this->expectException(SvgException::class);
        SvgColor::parse('hsl(120, 50%, 50%)');
    }
}
