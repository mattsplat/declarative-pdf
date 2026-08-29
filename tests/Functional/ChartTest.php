<?php

declare(strict_types=1);

namespace Pdf\Tests\Functional;

use Pdf\Chart\LegendPosition;
use Pdf\Chart\Series;
use Pdf\Color\Color;
use Pdf\Document;
use Pdf\Geometry\PageSize;
use Pdf\Geometry\ShrinkMode;
use Pdf\Geometry\Unit;
use Pdf\Node\Chart;
use Pdf\Tests\Support\Golden;
use Pdf\Tests\Support\Pdf;
use PHPUnit\Framework\TestCase;

final class ChartTest extends TestCase
{
    public function test_chart_document_matches_golden(): void
    {
        $pdf = Document::create()
            ->using(Pdf::deterministicRenderer())
            ->meta(fn ($m) => $m->title('Charts'))
            ->page(function ($p): void {
                $p->units(Unit::Mm);

                $p->heading(1, 'Charts');

                $p->chart(Chart::bar(
                    [Series::of('Revenue', [32, 58, 41, 76]), Series::of('Cost', [21, 30, 27, 44])],
                    ['Q1', 'Q2', 'Q3', 'Q4'],
                    150,
                    55,
                    legend: LegendPosition::Bottom,
                ));

                $p->chart(Chart::line(
                    [Series::of('Sessions', [120, 145, 138, 172, 190, 168, 205])],
                    ['Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat', 'Sun'],
                    150,
                    48,
                ));

                $p->chart(Chart::pie([45, 25, 18, 12], ['Direct', 'Search', 'Social', 'Referral'], 55));

                $p->chart(Chart::sparkline([12, 15, 11, 19, 17, 22, 20, 26], 140, 20, Color::fromHex('#2f6fbf')));
            })
            ->toString();

        Golden::assert('chart.pdf', $pdf);
    }

    public function test_bar_chart_emits_its_axis_ticks_and_category_labels_as_text(): void
    {
        $content = Pdf::contentText($this->barOnly());

        foreach (['(Q1) Tj', '(Q4) Tj', '(0) Tj', '(80) Tj', '(Revenue) Tj', '(Cost) Tj'] as $needle) {
            self::assertStringContainsString($needle, $content);
        }
    }

    public function test_bar_chart_draws_one_filled_rectangle_per_datum(): void
    {
        $pdf = Document::create()
            ->using(Pdf::deterministicRenderer())
            ->page(fn ($p) => $p
                ->size(PageSize::a4())->units(Unit::Mm)
                ->chart(Chart::bar(
                    [Series::of('Revenue', [32, 58, 41, 76]), Series::of('Cost', [21, 30, 27, 44])],
                    ['Q1', 'Q2', 'Q3', 'Q4'],
                    150,
                    55,
                )))
            ->toString();

        // Two series x four categories = eight bars, each a `re f` fill, and no
        // legend swatches to confuse the count.
        self::assertSame(8, substr_count(Pdf::contentText($pdf), ' re f Q'));
    }

    public function test_pie_slices_are_closed_filled_subpaths(): void
    {
        $pdf = Document::create()
            ->using(Pdf::deterministicRenderer())
            ->page(fn ($p) => $p
                ->size(PageSize::a4())->units(Unit::Pt)
                ->place(50, 50, 200, 200, [
                    Chart::pie([1, 1, 1, 1], size: 200, unit: Unit::Pt, legend: LegendPosition::None),
                ], shrink: ShrinkMode::None))
            ->toString();

        $content = Pdf::contentText($pdf);

        // Four equal slices, each a filled-and-stroked closed path (`h ... B`).
        self::assertSame(4, substr_count($content, "\nh\nB\nQ"));
    }

    public function test_a_chart_too_tall_for_the_page_moves_whole_to_the_next(): void
    {
        $pdf = Document::create()
            ->using(Pdf::deterministicRenderer())
            ->page(fn ($p) => $p
                ->size(PageSize::a4())->units(Unit::Pt)->margin(0)
                ->paragraph('Above.')
                ->chart(Chart::bar([Series::of('S', [1, 2, 3])], width: 200, height: 841.89, unit: Unit::Pt)))
            ->toString();

        self::assertStringContainsString("/Count 2\n", $pdf);
    }

    private function barOnly(): string
    {
        return Document::create()
            ->using(Pdf::deterministicRenderer())
            ->page(fn ($p) => $p
                ->size(PageSize::a4())->units(Unit::Mm)
                ->chart(Chart::bar(
                    [Series::of('Revenue', [32, 58, 41, 76]), Series::of('Cost', [21, 30, 27, 44])],
                    ['Q1', 'Q2', 'Q3', 'Q4'],
                    150,
                    55,
                    legend: LegendPosition::Bottom,
                )))
            ->toString();
    }
}
