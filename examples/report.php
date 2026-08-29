<?php

declare(strict_types=1);

require __DIR__ . '/../vendor/autoload.php';

use Pdf\Chart\LegendPosition;
use Pdf\Chart\Series;
use Pdf\Color\Color;
use Pdf\Document;
use Pdf\Geometry\Edges;
use Pdf\Geometry\Unit;
use Pdf\Layout\PageContext;
use Pdf\Node\Chart;
use Pdf\Node\Paragraph;
use Pdf\Node\Table;
use Pdf\Node\TableCell;
use Pdf\Node\TableRow;
use Pdf\Style\Border;
use Pdf\Style\ColumnWidth;
use Pdf\Style\StylePatch;
use Pdf\Style\TextAlign;

/*
 * A multi-page report: running header and footer, a bookmark outline built
 * from anchors, callout containers that split cleanly across pages, lists, a
 * data table with a totals row, and an embedded chart.
 */

$navy = Color::rgb(19, 33, 68);
$accent = Color::rgb(64, 120, 200);
$muted = Color::gray(115);

$callout = static fn (string $title, string $body): array => [
    new Paragraph($title, new StylePatch(bold: true, color: $navy, spaceAfterPt: 3.0)),
    new Paragraph($body),
];
$calloutPatch = new StylePatch(
    paddingPt: Edges::all(10.0),
    border: new Border(new Edges(0.0, 0.0, 0.0, 3.0), $accent),
    background: Color::rgb(244, 247, 252),
    spaceBeforePt: 10.0,
    spaceAfterPt: 10.0,
);

$doc = Document::create()
    ->meta(fn ($m) => $m
        ->title('Phase 2 Report')
        ->author('declarative-pdf')
        ->subject('Layout engine, quarter in review'))
    ->pageNumbers('Page {n} of {N}', TextAlign::Center, 8.5, $muted)
    ->bookmark('Summary', 'summary', 0)
    ->bookmark('Throughput', 'throughput', 0)
    ->bookmark('Risks', 'risks', 1)
    ->bookmark('Detail tables', 'tables', 0)
    ->bookmark('Appendix', 'appendix', 0);

$doc->page(function ($p) use ($navy, $accent, $muted, $callout, $calloutPatch): void {
    $p->header(fn (PageContext $c) => new Paragraph(
        'PHASE 2 REPORT   |   CONFIDENTIAL',
        new StylePatch(fontSizePt: 7.5, color: $muted, spaceAfterPt: 0.0),
    ));

    $p->anchor('summary');
    $p->heading(1, 'Phase 2 report', new StylePatch(color: $navy));
    $p->paragraph(
        'The layout engine reached feature parity with the imperative baseline '
        . 'this quarter and moved ahead of it: real pagination, automatic table '
        . 'sizing, embedded fonts, vector drawing, charts and interactive forms '
        . 'all landed.',
        new StylePatch(align: TextAlign::Justify, lineHeight: 1.45, spaceAfterPt: 8.0),
    );

    $p->container($callout(
        'Headline',
        'Every document in the example suite now renders through one code path, '
        . 'and the golden-file tests lock the output byte-for-byte.',
    ), $calloutPatch);

    $p->anchor('throughput');
    $p->heading(2, 'Throughput', new StylePatch(color: $navy));
    $p->paragraph('Tests and rendered examples, by month:', new StylePatch(spaceAfterPt: 4.0));
    $p->chart(Chart::bar(
        [
            Series::of('Tests', [162, 198, 239, 306], $accent),
            Series::of('Examples', [9, 11, 12, 16], Color::rgb(150, 180, 220)),
        ],
        ['May', 'Jun', 'Jul', 'Aug'],
        150.0,
        52.0,
        Unit::Mm,
        LegendPosition::Bottom,
    ));

    $p->anchor('risks');
    $p->heading(3, 'Risks', new StylePatch(color: Color::gray(70)));
    $p->bulletList([
        'CFF fonts still embed whole; subsetting is the next size win.',
        'PDF JavaScript reaches only Acrobat — calculators must degrade to a '
            . 'usable blank form everywhere else.',
        'The importer is single-page; a true document merge is planned.',
    ]);

    $p->pageBreak();

    $p->anchor('tables');
    $p->heading(2, 'Detail tables', new StylePatch(color: $navy));
    $p->paragraph('A table sizes its columns to content and repeats its header on '
        . 'every page it spans. Numeric columns are right-aligned; the last row is '
        . 'a total.', new StylePatch(spaceAfterPt: 6.0));

    $lines = [
        ['Measurer', 41, 1.9],
        ['LineBreaker', 33, 1.1],
        ['Paginator', 58, 3.4],
        ['TableLayout', 44, 2.2],
        ['DocumentRenderer', 96, 5.1],
    ];
    $rows = [
        new TableRow([
            new TableCell('Component'),
            new TableCell('Tests', patch: new StylePatch(align: TextAlign::Right)),
            new TableCell('kLOC', patch: new StylePatch(align: TextAlign::Right)),
        ]),
    ];
    $totalTests = 0;
    $totalLoc = 0.0;
    foreach ($lines as [$name, $tests, $loc]) {
        $totalTests += $tests;
        $totalLoc += $loc;
        $rows[] = new TableRow([
            new TableCell($name),
            new TableCell((string) $tests, patch: new StylePatch(align: TextAlign::Right)),
            new TableCell(number_format($loc, 1), patch: new StylePatch(align: TextAlign::Right)),
        ]);
    }
    $rows[] = new TableRow([
        new TableCell('Total', patch: new StylePatch(bold: true)),
        new TableCell((string) $totalTests, patch: new StylePatch(bold: true, align: TextAlign::Right)),
        new TableCell(number_format($totalLoc, 1), patch: new StylePatch(bold: true, align: TextAlign::Right)),
    ]);

    $p->add(new Table(
        $rows,
        [ColumnWidth::fraction(1.0), ColumnWidth::fixed(60.0), ColumnWidth::fixed(60.0)],
        headerRows: 1,
        headerBackground: Color::rgb(230, 236, 245),
    ));

    $p->anchor('appendix');
    $p->heading(2, 'Appendix', new StylePatch(color: $navy, spaceBeforePt: 16.0));
    $p->orderedList([
        'Methodology: counts taken from composer test and the examples/ folder.',
        'All figures exclude vendor/ and generated fixtures.',
        'Prior-quarter numbers restated to the new component boundaries.',
    ]);
});

$doc->save(__DIR__ . '/report.pdf');

echo 'Wrote ' . __DIR__ . "/report.pdf\n";
