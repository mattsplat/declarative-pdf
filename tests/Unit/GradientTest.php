<?php

declare(strict_types=1);

namespace Pdf\Tests\Unit;

use Pdf\Color\Color;
use Pdf\Exception\PdfException;
use Pdf\Style\GradientSpread;
use Pdf\Style\GradientStop;
use Pdf\Style\LinearGradient;
use Pdf\Style\RadialGradient;
use PHPUnit\Framework\TestCase;

final class GradientTest extends TestCase
{
    /** @return \Closure(float, float): array{0: float, 1: float} */
    private function identityPlace(): \Closure
    {
        return static fn (float $x, float $y): array => [$x, $y];
    }

    public function test_a_gradient_needs_at_least_two_stops(): void
    {
        $this->expectException(PdfException::class);

        LinearGradient::horizontal([new GradientStop(0.0, Color::black())]);
    }

    public function test_stop_offsets_must_not_decrease(): void
    {
        $this->expectException(PdfException::class);

        LinearGradient::horizontal([
            new GradientStop(0.6, Color::black()),
            new GradientStop(0.3, Color::white()),
        ]);
    }

    public function test_stops_are_normalised_to_span_zero_to_one(): void
    {
        $gradient = LinearGradient::horizontal([
            new GradientStop(0.25, Color::black()),
            new GradientStop(0.75, Color::white()),
        ]);

        self::assertSame([0.0, 0.25, 0.75, 1.0], array_map(
            static fn (GradientStop $s): float => $s->offset,
            $gradient->stops,
        ));
    }

    public function test_two_stops_use_a_single_exponential_function(): void
    {
        $gradient = LinearGradient::horizontal([
            new GradientStop(0.0, Color::rgb(255, 0, 0)),
            new GradientStop(1.0, Color::rgb(0, 0, 255)),
        ]);

        self::assertSame(
            '<</FunctionType 2 /Domain [0 1] /C0 [1.0000 0.0000 0.0000] /C1 [0.0000 0.0000 1.0000] /N 1>>',
            $gradient->functionDictionary(),
        );
    }

    public function test_three_stops_stitch_two_exponential_segments(): void
    {
        $gradient = LinearGradient::horizontal([
            new GradientStop(0.0, Color::black()),
            new GradientStop(0.4, Color::gray(128)),
            new GradientStop(1.0, Color::white()),
        ]);

        $dict = $gradient->functionDictionary();
        self::assertStringStartsWith('<</FunctionType 3 /Domain [0 1] /Functions [', $dict);
        self::assertStringContainsString('/Bounds [0.40000]', $dict);
        self::assertStringContainsString('/Encode [0 1 0 1]', $dict);
        self::assertSame(2, substr_count($dict, '/FunctionType 2'));
    }

    public function test_linear_coords_are_the_axis_scaled_to_the_box(): void
    {
        $gradient = LinearGradient::horizontal([
            new GradientStop(0.0, Color::black()),
            new GradientStop(1.0, Color::white()),
        ]);

        self::assertSame([0.0, 10.0, 200.0, 10.0], $gradient->coords($this->identityPlace(), 200.0, 20.0));
        self::assertSame(2, $gradient->shadingType());
    }

    public function test_radial_coords_put_the_reference_radius_on_the_larger_side(): void
    {
        $gradient = RadialGradient::centered(
            [new GradientStop(0.0, Color::white()), new GradientStop(1.0, Color::black())],
            radius: 0.5,
        );

        // reference = max(200, 100) = 200, so radius = 100; both circles centred.
        self::assertSame(
            [100.0, 50.0, 0.0, 100.0, 50.0, 100.0],
            $gradient->coords($this->identityPlace(), 200.0, 100.0),
        );
        self::assertSame(3, $gradient->shadingType());
    }

    public function test_spread_none_disables_extend(): void
    {
        $gradient = LinearGradient::vertical(
            [new GradientStop(0.0, Color::black()), new GradientStop(1.0, Color::white())],
            GradientSpread::None,
        );

        self::assertSame('false false', $gradient->spread->extendArray());
    }
}
