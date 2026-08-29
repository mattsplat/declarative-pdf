<?php

declare(strict_types=1);

require __DIR__ . '/../vendor/autoload.php';

use Pdf\Chart\LegendPosition;
use Pdf\Chart\Series;
use Pdf\Color\Color;
use Pdf\Document;
use Pdf\Font\FontFace;
use Pdf\Geometry\Unit;
use Pdf\Layout\PageContext;
use Pdf\Node\Chart;
use Pdf\Node\Paragraph;
use Pdf\Node\Table;
use Pdf\Node\TableCell;
use Pdf\Node\TableRow;
use Pdf\Node\Watermark;
use Pdf\Style\ColumnWidth;
use Pdf\Style\StylePatch;
use Pdf\Style\TextAlign;

/*
 * Document furniture applied once:
 *
 *   ->watermark('DRAFT')   diagonal, translucent, on every sheet
 *   ->pageNumbers(...)      "Page n of N" in every footer
 *
 * A page can still replace the document watermark with its own — page 3 does,
 * swapping DRAFT for a red CONFIDENTIAL stamp.
 */

$navy = Color::rgb(20, 34, 66);
$muted = Color::gray(120);

$runningHeader = fn (string $section) => fn (PageContext $c) => new Paragraph(
    "RIVERSIDE HOLDINGS   ·   BOARD PACK Q3   ·   {$section}",
    new StylePatch(fontSizePt: 7.5, color: $muted, spaceAfterPt: 0.0),
);

Document::create()
    ->meta(fn ($m) => $m->title('Board pack Q3 (draft)')->author('Company Secretary'))
    ->watermark('DRAFT')
    ->pageNumbers('Page {n} of {N}', TextAlign::Center, 8.0, $muted)
    ->page(function ($p) use ($navy, $runningHeader): void {
        $p->header($runningHeader('Agenda'));
        $p->heading(1, 'Board meeting — agenda', new StylePatch(color: $navy));
        $p->paragraph('Thursday, 10:00. This copy carries a diagonal DRAFT '
            . 'watermark and a page number, both set once at the document level.');
        $p->orderedList([
            'Apologies and declarations of interest.',
            'Minutes of the previous meeting.',
            'CEO report and Q3 numbers.',
            'Capital plan — approval sought.',
            'Risk register review.',
            'Any other business.',
        ], patch: new StylePatch(spaceBeforePt: 6.0));
    })
    ->page(function ($p) use ($navy, $runningHeader): void {
        $p->header($runningHeader('Q3 numbers'));
        $p->heading(2, 'Q3 numbers', new StylePatch(color: $navy));
        $p->paragraph('Revenue and operating cost by month (£m):',
            new StylePatch(spaceAfterPt: 4.0));
        $p->chart(Chart::bar(
            [
                Series::of('Revenue', [4.1, 4.6, 5.2], Color::rgb(64, 120, 200)),
                Series::of('Op. cost', [3.0, 3.1, 3.3], Color::rgb(150, 180, 220)),
            ],
            ['Jul', 'Aug', 'Sep'],
            150.0,
            50.0,
            Unit::Mm,
            LegendPosition::Bottom,
        ));

        $right = new StylePatch(align: TextAlign::Right);
        $p->add(new Table(
            [
                new TableRow([
                    new TableCell('Metric', patch: new StylePatch(bold: true)),
                    new TableCell('Q2', patch: new StylePatch(bold: true, align: TextAlign::Right)),
                    new TableCell('Q3', patch: new StylePatch(bold: true, align: TextAlign::Right)),
                ]),
                new TableRow([new TableCell('Revenue (£m)'), new TableCell('12.4', patch: $right), new TableCell('13.9', patch: $right)]),
                new TableRow([new TableCell('EBITDA (£m)'), new TableCell('3.1', patch: $right), new TableCell('3.5', patch: $right)]),
                new TableRow([new TableCell('Cash (£m)'), new TableCell('8.0', patch: $right), new TableCell('9.2', patch: $right)]),
            ],
            [ColumnWidth::fraction(1.0), ColumnWidth::fixed(60.0), ColumnWidth::fixed(60.0)],
            headerRows: 1,
            headerBackground: Color::rgb(232, 237, 244),
        ));
    })
    ->page(function ($p) use ($navy, $runningHeader): void {
        $p->header($runningHeader('Appendix A'));
        $p->watermark(new Watermark(
            'CONFIDENTIAL',
            color: Color::rgb(170, 20, 20),
            opacity: 0.10,
            angleDeg: 45.0,
            fontFace: new FontFace(700),
        ));
        $p->heading(2, 'Appendix A — capital plan detail', new StylePatch(color: $navy));
        $p->paragraph('This page overrides the document watermark with a red, '
            . 'low-opacity CONFIDENTIAL stamp. Everything else — header, footer, '
            . 'page number — is inherited.', new StylePatch(spaceAfterPt: 6.0));
        $p->paragraph(str_repeat('Line-item commentary supporting the capital '
            . 'request, continued at length so the page fills. ', 22),
            new StylePatch(align: TextAlign::Justify));
    })
    ->save(__DIR__ . '/watermark.pdf');

echo 'Wrote ' . __DIR__ . "/watermark.pdf\n";
