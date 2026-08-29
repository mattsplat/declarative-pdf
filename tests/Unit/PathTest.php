<?php

declare(strict_types=1);

namespace Pdf\Tests\Unit;

use Pdf\Color\Color;
use Pdf\Geometry\Edges;
use Pdf\Geometry\Orientation;
use Pdf\Geometry\PageGeometry;
use Pdf\Geometry\PageSize;
use Pdf\Geometry\PathOp;
use Pdf\Geometry\Point;
use Pdf\Geometry\Unit;
use Pdf\Node\Path;
use Pdf\Render\ContentStream;
use Pdf\Style\FillRule;
use Pdf\Style\LineCap;
use Pdf\Style\LineJoin;
use Pdf\Style\Paint;
use PHPUnit\Framework\TestCase;

final class PathTest extends TestCase
{
    /** Draw a path at the page origin and return the operators it emitted. */
    private function draw(Path $path): string
    {
        $stream = new ContentStream(
            new PageGeometry(PageSize::a4(), Orientation::Portrait, new Edges()),
            emitPreamble: false,
        );
        $stream->path($path->commands, 0.0, 0.0, $path->paint);

        return $stream->toString();
    }

    public function test_rectangle_emits_the_four_corner_segments_and_closes(): void
    {
        $path = Path::rectangle(100.0, 40.0, Paint::filled(Color::black()), Unit::Pt);

        self::assertSame(
            [PathOp::MoveTo, PathOp::LineTo, PathOp::LineTo, PathOp::LineTo, PathOp::Close],
            array_map(static fn ($c) => $c->op, $path->commands),
        );

        // Same corners an `re` would produce, in top-left coordinates.
        self::assertSame(
            "q 0.000 g\n0.00 841.89 m\n100.00 841.89 l\n100.00 801.89 l\n0.00 801.89 l\nh\nf\nQ",
            $this->draw($path),
        );
    }

    public function test_a_stroked_rectangle_is_inset_by_half_the_stroke_width(): void
    {
        // A 4pt stroke straddles its line, so the geometry sits 2pt in and the
        // painted outer edge lands exactly on the declared 100x40 box.
        $path = Path::rectangle(100.0, 40.0, Paint::stroked(Color::black(), 4.0), Unit::Pt);

        self::assertSame(100.0, $path->widthPt);
        self::assertSame(40.0, $path->heightPt);
        self::assertSame(
            "q 0.000 G 4.00 w 0 J 0 j\n2.00 839.89 m\n98.00 839.89 l\n98.00 803.89 l\n2.00 803.89 l\nh\nS\nQ",
            $this->draw($path),
        );
    }

    public function test_a_filled_shape_is_not_inset(): void
    {
        $path = Path::rectangle(100.0, 40.0, Paint::filled(Color::black()), Unit::Pt);

        self::assertStringContainsString("0.00 841.89 m\n100.00 841.89 l", $this->draw($path));
    }

    public function test_ellipse_is_four_cubic_beziers(): void
    {
        $path = Path::ellipse(80.0, 40.0, Paint::filled(Color::black()), Unit::Pt);

        self::assertSame(4, substr_count($this->draw($path), " c\n"));
        self::assertSame(
            [PathOp::MoveTo, PathOp::CurveTo, PathOp::CurveTo, PathOp::CurveTo, PathOp::CurveTo, PathOp::Close],
            array_map(static fn ($c) => $c->op, $path->commands),
        );
    }

    public function test_ellipse_control_points_use_the_kappa_approximation(): void
    {
        $path = Path::ellipse(100.0, 100.0, Paint::filled(Color::black()), Unit::Pt);
        $firstCurve = $path->commands[1];

        // Starting at the 3 o'clock point, the first control point is kappa * r below it.
        self::assertEqualsWithDelta(100.0, $firstCurve->points[0]->x, 1e-9);
        self::assertEqualsWithDelta(50.0 + 50.0 * 0.5523, $firstCurve->points[0]->y, 1e-9);
    }

    public function test_fill_stroke_and_both_pick_the_right_painting_operator(): void
    {
        $commands = Path::rectangle(10.0, 10.0, Paint::filled(Color::black()), Unit::Pt)->commands;

        $filled = new Path($commands, 10.0, 10.0, Paint::filled(Color::black()));
        $stroked = new Path($commands, 10.0, 10.0, Paint::stroked(Color::black(), 2.0));
        $both = new Path($commands, 10.0, 10.0, new Paint(fill: Color::white(), stroke: Color::black()));

        self::assertStringEndsWith("\nf\nQ", $this->draw($filled));
        self::assertStringEndsWith("\nS\nQ", $this->draw($stroked));
        self::assertStringEndsWith("\nB\nQ", $this->draw($both));
    }

    public function test_even_odd_fill_rule_stars_the_painting_operator(): void
    {
        $commands = Path::rectangle(10.0, 10.0, Paint::filled(Color::black()), Unit::Pt)->commands;

        $filled = new Path($commands, 10.0, 10.0, Paint::filled(Color::black(), FillRule::EvenOdd));
        $both = new Path($commands, 10.0, 10.0, new Paint(
            fill: Color::white(),
            stroke: Color::black(),
            fillRule: FillRule::EvenOdd,
        ));

        self::assertStringEndsWith("\nf*\nQ", $this->draw($filled));
        self::assertStringEndsWith("\nB*\nQ", $this->draw($both));
    }

    public function test_stroke_state_names_width_cap_and_join(): void
    {
        $path = Path::line(0.0, 0.0, 10.0, 0.0, new Paint(
            stroke: Color::rgb(255, 0, 0),
            strokeWidthPt: 2.5,
            lineCap: LineCap::Round,
            lineJoin: LineJoin::Bevel,
        ), Unit::Pt);

        self::assertStringStartsWith('q 1.000 0.000 0.000 RG 2.50 w 1 J 2 j', $this->draw($path));
    }

    public function test_a_paint_that_marks_nothing_emits_nothing(): void
    {
        $commands = Path::rectangle(10.0, 10.0, Paint::filled(Color::black()), Unit::Pt)->commands;
        $invisible = new Path($commands, 10.0, 10.0, new Paint(stroke: Color::black(), strokeWidthPt: 0.0));

        self::assertSame('', $this->draw($invisible));
    }

    public function test_an_unpainted_path_defaults_to_a_hairline_outline(): void
    {
        $paint = new Paint();

        self::assertFalse($paint->fills());
        self::assertTrue($paint->strokes());
        self::assertSame('S', $paint->operator());
    }

    public function test_factory_coordinates_are_converted_from_user_units(): void
    {
        $path = Path::rectangle(10.0, 5.0, Paint::filled(Color::black()), Unit::Mm);

        self::assertEqualsWithDelta(28.3464, $path->widthPt, 1e-4);
        self::assertEqualsWithDelta(14.1732, $path->heightPt, 1e-4);
        self::assertEqualsWithDelta(28.3464, $path->commands[1]->points[0]->x, 1e-4);
    }

    public function test_a_flat_line_still_reserves_its_stroke_width(): void
    {
        $path = Path::line(0.0, 0.0, 100.0, 0.0, Paint::stroked(Color::black(), 3.0), Unit::Pt);

        self::assertSame(100.0, $path->widthPt);
        self::assertSame(3.0, $path->heightPt);
    }

    public function test_polygon_closes_back_to_its_first_point(): void
    {
        $path = Path::polygon(
            [new Point(0.0, 0.0), new Point(20.0, 0.0), new Point(10.0, 16.0)],
            Paint::filled(Color::black()),
            Unit::Pt,
        );

        self::assertSame(
            [PathOp::MoveTo, PathOp::LineTo, PathOp::LineTo, PathOp::Close],
            array_map(static fn ($c) => $c->op, $path->commands),
        );
        self::assertSame(20.0, $path->widthPt);
        self::assertSame(16.0, $path->heightPt);
    }

    public function test_a_line_along_the_box_top_draws_at_the_top_of_the_page(): void
    {
        $path = Path::line(0.0, 0.0, 100.0, 0.0, Paint::stroked(Color::black(), 1.0), Unit::Pt);
        $stream = new ContentStream(
            new PageGeometry(PageSize::a4(), Orientation::Portrait, new Edges()),
            emitPreamble: false,
        );
        $stream->path($path->commands, 0.0, 20.0, $path->paint);

        // 20pt below the page top is 821.89 up from the bottom, not 20 — less
        // the 0.5pt inset that keeps the 1pt stroke inside the box.
        self::assertStringContainsString("0.50 821.39 m\n99.50 821.39 l", $stream->toString());
    }
}
