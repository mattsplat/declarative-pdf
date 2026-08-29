<?php

declare(strict_types=1);

namespace Pdf\Tests\Functional;

use Pdf\Color\Color;
use Pdf\Document;
use Pdf\Geometry\PageSize;
use Pdf\Geometry\Point;
use Pdf\Geometry\ShrinkMode;
use Pdf\Geometry\Unit;
use Pdf\Node\Path;
use Pdf\Style\FillRule;
use Pdf\Style\Paint;
use Pdf\Style\StylePatch;
use Pdf\Tests\Support\Golden;
use Pdf\Tests\Support\Pdf;
use PHPUnit\Framework\TestCase;

final class DrawingTest extends TestCase
{
    public function test_shapes_document_matches_golden(): void
    {
        $bars = [32.0, 58.0, 41.0, 76.0];
        $palette = [
            Color::fromHex('#2f6fbf'),
            Color::fromHex('#3f9d5a'),
            Color::fromHex('#d9803c'),
            Color::fromHex('#8a4fbf'),
        ];

        $star = [];
        for ($k = 0; $k < 5; $k++) {
            $angle = deg2rad($k * 144 - 90);
            $star[] = new Point(15.0 + 15.0 * cos($angle), 15.0 + 15.0 * sin($angle));
        }

        $pdf = Document::create()
            ->using(Pdf::deterministicRenderer())
            ->meta(fn ($m) => $m->title('Vector drawing'))
            ->page(function ($p) use ($bars, $palette, $star) {
                $p->units(Unit::Mm);

                $p->heading(1, 'Vector drawing');
                $p->paragraph('Solid fills and strokes, in flow and absolutely placed.');

                $p->path(Path::rectangle(60, 14, Paint::filled($palette[0]), patch: new StylePatch(spaceAfterPt: 8.0)));
                $p->path(Path::ellipse(60, 18, new Paint(
                    fill: Color::fromHex('#e8eef8'),
                    stroke: $palette[0],
                    strokeWidthPt: 1.5,
                ), patch: new StylePatch(spaceAfterPt: 8.0)));
                $p->path(Path::line(0, 0, 170, 0, Paint::stroked(Color::gray(140), 1.0)));

                $p->spacer(40);

                $p->place(20, 110, 30, 30, [
                    Path::polygon($star, Paint::filled($palette[2], FillRule::EvenOdd)),
                ], shrink: ShrinkMode::None);
                $p->place(60, 110, 30, 30, [
                    Path::ellipse(30, 30, Paint::filled($palette[3])),
                ], shrink: ShrinkMode::None);

                foreach ($bars as $index => $value) {
                    $height = 55.0 * $value / 80.0;
                    $p->place(22 + $index * 40.0, 165.0 + 55.0 - $height, 26.0, $height, [
                        Path::rectangle(26.0, $height, Paint::filled($palette[$index])),
                    ], shrink: ShrinkMode::None);
                }

                $p->place(22, 220, 152, 1, [
                    Path::line(0, 0, 152, 0, Paint::stroked(Color::gray(60), 1.0)),
                ], shrink: ShrinkMode::None);
            })
            ->toString();

        Golden::assert('shapes.pdf', $pdf);
    }

    public function test_placed_path_lands_in_its_rectangle(): void
    {
        $pdf = Document::create()
            ->using(Pdf::deterministicRenderer())
            ->page(fn ($p) => $p
                ->size(PageSize::a4())->units(Unit::Pt)
                ->place(100, 100, 200, 200, [
                    Path::rectangle(200, 200, Paint::filled(Color::black()), Unit::Pt),
                ], shrink: ShrinkMode::None))
            ->toString();

        $content = Pdf::contentText($pdf);

        // The area's own origin: x = 100, and its bottom edge (100 + 200) flipped.
        self::assertStringContainsString('q 1.0000 0 0 1.0000 100.00 541.89 cm', $content);
        // Inside that matrix the rectangle spans the full 200x200 sub-space.
        self::assertStringContainsString("0.00 200.00 m\n200.00 200.00 l\n200.00 0.00 l\n0.00 0.00 l\nh\nf", $content);
    }

    public function test_path_stacks_below_a_paragraph_in_block_flow(): void
    {
        $pdf = Document::create()
            ->using(Pdf::deterministicRenderer())
            ->page(fn ($p) => $p
                ->size(PageSize::a4())->units(Unit::Pt)->margin(0)
                ->paragraph('Above.', new StylePatch(fontSizePt: 12.0, spaceAfterPt: 0.0))
                ->path(Path::rectangle(50, 20, Paint::filled(Color::black()), Unit::Pt)))
            ->toString();

        $content = Pdf::contentText($pdf);
        self::assertSame(1, preg_match('/\n(\d+\.\d+) (\d+\.\d+) m\n/', $content, $match));

        // The paragraph occupies one 12pt line at the default 1.15 line height,
        // so the path's box top is 13.8pt below the page top.
        self::assertEqualsWithDelta(841.89 - 13.8, (float) $match[2], 0.01);
    }

    public function test_a_line_across_the_top_of_its_box_renders_at_the_top_of_the_page(): void
    {
        $pdf = Document::create()
            ->using(Pdf::deterministicRenderer())
            ->page(fn ($p) => $p
                ->size(PageSize::a4())->units(Unit::Pt)
                ->path(Path::line(0, 0, 100, 0, Paint::stroked(Color::black(), 1.0), Unit::Pt)))
            ->toString();

        $content = Pdf::contentText($pdf);
        self::assertSame(1, preg_match('/\n28\.85 (\d+\.\d+) m\n/', $content, $match));

        // Drawn just inside the top margin: the 1pt stroke's centre line sits
        // 0.5pt below 28.35, so its ink reaches the margin and no further —
        // and it is in the top tenth of the sheet, not mirrored to the bottom.
        self::assertEqualsWithDelta(813.04, (float) $match[1], 0.01);
        self::assertGreaterThan(841.89 * 0.9, (float) $match[1]);
    }

    public function test_a_path_too_tall_for_the_remaining_space_moves_to_the_next_page(): void
    {
        $pdf = Document::create()
            ->using(Pdf::deterministicRenderer())
            ->page(fn ($p) => $p
                ->size(PageSize::a4())->units(Unit::Pt)->margin(0)
                ->paragraph('Above.', new StylePatch(fontSizePt: 12.0, spaceAfterPt: 0.0))
                // 841.89 tall: it fits a whole page, but not the 828.09 left
                // under the paragraph, and it must never be split.
                ->path(Path::rectangle(50, 841.89, Paint::filled(Color::black()), Unit::Pt)))
            ->toString();

        self::assertStringContainsString("/Count 2\n", $pdf);

        // One un-split rectangle, on the second page, at that page's top.
        $content = Pdf::contentText($pdf);
        self::assertSame(1, substr_count($content, "\nh\nf\n"));
        self::assertStringContainsString("0.00 841.89 m\n50.00 841.89 l", $content);
    }
}
