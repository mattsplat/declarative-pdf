<?php

declare(strict_types=1);

namespace Pdf\Tests\Unit;

use Pdf\Color\Color;
use Pdf\Geometry\Edges;
use Pdf\Geometry\Orientation;
use Pdf\Geometry\PageGeometry;
use Pdf\Geometry\PageSize;
use Pdf\Geometry\PathCommand;
use Pdf\Geometry\Unit;
use Pdf\Node\Path;
use Pdf\Render\ContentStream;
use Pdf\Style\FillRule;
use Pdf\Style\GradientStop;
use Pdf\Style\LinearGradient;
use Pdf\Style\Paint;
use Pdf\Style\RadialGradient;
use PHPUnit\Framework\TestCase;

final class VectorClipTest extends TestCase
{
    private function stream(): ContentStream
    {
        return new ContentStream(
            new PageGeometry(PageSize::a4(), Orientation::Portrait, new Edges()),
            emitPreamble: false,
        );
    }

    public function test_a_gradient_fill_clips_to_the_path_and_paints_a_shading(): void
    {
        $path = Path::rectangle(100.0, 40.0, Paint::gradient(LinearGradient::horizontal([
            new GradientStop(0.0, Color::black()),
            new GradientStop(1.0, Color::white()),
        ])), Unit::Pt);

        $stream = $this->stream();
        $stream->path($path->commands, 0.0, 0.0, $path->paint, $path->widthPt, $path->heightPt);

        $out = $stream->toString();
        self::assertStringContainsString("h\nW n\n/Sh1 sh\nQ", $out);

        $shadings = $stream->collectedShadings();
        self::assertCount(1, $shadings);
        self::assertSame('Sh1', $shadings[0]->name);
        self::assertStringContainsString('/ShadingType 2', $shadings[0]->dictionary);
        // Axis endpoints span the box width at mid-height, Y flipped once.
        self::assertStringContainsString('/Coords [0.00 821.89 100.00 821.89]', $shadings[0]->dictionary);
    }

    public function test_a_radial_gradient_emits_a_type_3_shading(): void
    {
        $path = Path::ellipse(60.0, 60.0, Paint::gradient(RadialGradient::centered([
            new GradientStop(0.0, Color::white()),
            new GradientStop(1.0, Color::black()),
        ])), Unit::Pt);

        $stream = $this->stream();
        $stream->path($path->commands, 0.0, 0.0, $path->paint, $path->widthPt, $path->heightPt);

        self::assertStringContainsString('/ShadingType 3', $stream->collectedShadings()[0]->dictionary);
    }

    public function test_a_gradient_paint_that_also_strokes_makes_a_second_stroking_pass(): void
    {
        $paint = new Paint(
            fill: LinearGradient::vertical([
                new GradientStop(0.0, Color::black()),
                new GradientStop(1.0, Color::white()),
            ]),
            stroke: Color::rgb(255, 0, 0),
            strokeWidthPt: 2.0,
        );
        $commands = Path::rectangle(20.0, 20.0, Paint::filled(Color::black()), Unit::Pt)->commands;

        $stream = $this->stream();
        $stream->path($commands, 0.0, 0.0, $paint, 20.0, 20.0);

        $out = $stream->toString();
        self::assertStringContainsString('/Sh1 sh', $out);
        self::assertStringContainsString('1.000 0.000 0.000 RG 2.00 w', $out);
        self::assertStringEndsWith("\nS\nQ", $out);
    }

    public function test_with_path_clip_wraps_the_drawing_in_a_clip_group(): void
    {
        $stream = $this->stream();
        $commands = [
            PathCommand::moveTo(0.0, 0.0),
            PathCommand::lineTo(30.0, 0.0),
            PathCommand::lineTo(15.0, 30.0),
            PathCommand::close(),
        ];

        $stream->withPathClip($commands, 10.0, 10.0, FillRule::EvenOdd, static function () use ($stream): void {
            $stream->fillRect(0.0, 0.0, 5.0, 5.0, Color::black());
        });

        $out = $stream->toString();
        self::assertStringContainsString("10.00 831.89 m\n40.00 831.89 l\n25.00 801.89 l\nh\nW* n", $out);
        self::assertStringEndsWith("\nQ", $out);
    }

    public function test_with_path_clip_short_circuits_on_an_empty_command_list(): void
    {
        $stream = $this->stream();
        $ran = false;
        $stream->withPathClip([], 0.0, 0.0, FillRule::NonZero, static function () use (&$ran): void {
            $ran = true;
        });

        self::assertTrue($ran);
        self::assertStringNotContainsString('W n', $stream->toString());
    }
}
