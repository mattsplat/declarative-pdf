<?php

declare(strict_types=1);

namespace Pdf\Tests\Functional;

use Pdf\Document;
use Pdf\Layout\LineBreaker;
use Pdf\Layout\Measurer;
use Pdf\Layout\Paginator;
use Pdf\Node\Document as DocumentTree;
use Pdf\Node\Page;
use Pdf\Node\Table;
use Pdf\Node\TableCell;
use Pdf\Node\TableRow;
use Pdf\Style\ColumnWidth;
use Pdf\Style\StyleResolver;
use Pdf\Style\VerticalAlign;
use Pdf\Tests\Support\Fonts;
use Pdf\Tests\Support\Golden;
use Pdf\Tests\Support\Pdf;
use PHPUnit\Framework\TestCase;

final class TableTest extends TestCase
{
    private function paginator(): Paginator
    {
        return new Paginator(new Measurer(new StyleResolver(), Fonts::registry(), new LineBreaker()));
    }

    /** @param list<array<int, string>> $data */
    private function simpleTable(array $data, int $headerRows = 1, ?array $columns = null): Table
    {
        $rows = array_map(
            static fn (array $cells) => new TableRow(array_map(
                static fn (string $c) => new TableCell($c),
                $cells,
            )),
            $data,
        );

        return new Table($rows, $columns, headerRows: $headerRows);
    }

    public function test_renders_a_table_with_grid_borders_and_text(): void
    {
        $pdf = Document::create()
            ->using(Pdf::deterministicRenderer())
            ->page(fn ($p) => $p->table([
                new TableRow(['Country', 'Capital', 'Area']),
                new TableRow(['Austria', 'Vienna', '83859']),
                new TableRow(['Belgium', 'Brussels', '30518']),
            ], headerRows: 1))
            ->toString();

        $content = Pdf::contentText($pdf);
        self::assertStringContainsString('(Country) Tj', $content);
        self::assertStringContainsString('(Vienna) Tj', $content);
        // Grid lines are drawn as thin filled rectangles in the border colour.
        self::assertMatchesRegularExpression('/q 0\.000 g [\d.]+ [\d.]+ [\d.]+ 0\.50 re f Q/', $content);
    }

    public function test_auto_sizing_gives_more_room_to_the_wider_column(): void
    {
        $tree = new DocumentTree([
            new Page(children: [
                $this->simpleTable([
                    ['A', 'This column has much longer content that needs more width'],
                    ['x', 'y'],
                ], headerRows: 0),
            ]),
        ]);

        $pages = $this->paginator()->paginate($tree);
        $content = Pdf::contentText(Pdf::deterministicRenderer()->render($tree));

        self::assertCount(1, $pages);
        self::assertStringContainsString('much longer content', $content);
    }

    public function test_long_table_paginates_and_repeats_the_header(): void
    {
        $rows = [['#', 'Item']];
        for ($i = 1; $i <= 80; $i++) {
            $rows[] = [(string) $i, "Row item number {$i} with a bit of descriptive text"];
        }

        $pdf = Document::create()
            ->using(Pdf::deterministicRenderer())
            ->page(fn ($p) => $p->table(
                array_map(
                    static fn ($r) => new TableRow(array_map(static fn ($c) => new TableCell($c), $r)),
                    $rows,
                ),
                headerRows: 1,
            ))
            ->toString();

        $content = Pdf::contentText($pdf);
        // The header cell text appears once per page.
        $pageCount = substr_count($pdf, '/Type /Page') - substr_count($pdf, '/Type /Pages');
        self::assertGreaterThan(1, $pageCount);
        self::assertSame($pageCount, substr_count($content, '(Item) Tj'));
    }

    public function test_fixed_and_fraction_columns_are_honoured(): void
    {
        $measurer = new Measurer(new StyleResolver(), Fonts::registry(), new LineBreaker());
        $table = new Table(
            [new TableRow(['a', 'b', 'c'])],
            [ColumnWidth::fixed(60.0), ColumnWidth::fraction(1.0), ColumnWidth::fraction(2.0)],
            totalWidthPt: 300.0,
        );

        $box = $measurer->measureBlock($table, 500.0, \Pdf\Style\Style::default());

        self::assertInstanceOf(\Pdf\Layout\Box\TableBox::class, $box);
        // Total width honoured; fixed column exact; fractions split the rest 1:2.
        self::assertEqualsWithDelta(300.0, $box->maxIntrinsicWidthPt(), 1e-4);
    }

    public function test_vertical_align_and_colspan_do_not_crash(): void
    {
        $pdf = Document::create()
            ->using(Pdf::deterministicRenderer())
            ->page(fn ($p) => $p->table([
                new TableRow([new TableCell('Spans two columns', colspan: 2), new TableCell('Third')]),
                new TableRow([
                    new TableCell('Tall cell with several lines of text so the row grows in height'),
                    new TableCell('mid', verticalAlign: VerticalAlign::Middle),
                    new TableCell('bottom', verticalAlign: VerticalAlign::Bottom),
                ]),
            ]))
            ->toString();

        self::assertStringStartsWith('%PDF-', $pdf);
        self::assertStringContainsString('(Spans two columns) Tj', Pdf::contentText($pdf));
    }

    public function test_table_document_is_byte_stable(): void
    {
        $pdf = Document::create()
            ->using(Pdf::deterministicRenderer())
            ->meta(fn ($m) => $m->title('Tables'))
            ->page(function ($p) {
                $p->heading(1, 'Countries');
                $p->table([
                    new TableRow(['Country', 'Capital', 'Area (km2)', 'Pop. (x1000)']),
                    new TableRow(['Austria', 'Vienna', '83859', '8075']),
                    new TableRow(['Belgium', 'Brussels', '30518', '10192']),
                    new TableRow(['Denmark', 'Copenhagen', '43094', '5295']),
                    new TableRow(['France', 'Paris', '543965', '58728']),
                ], headerRows: 1);
            })
            ->toString();

        Golden::assert('tables.pdf', $pdf);
    }
}
