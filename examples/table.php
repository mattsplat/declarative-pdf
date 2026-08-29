<?php

declare(strict_types=1);

require __DIR__ . '/../vendor/autoload.php';

use Pdf\Color\Color;
use Pdf\Document;
use Pdf\Geometry\Edges;
use Pdf\Layout\PageContext;
use Pdf\Node\Paragraph;
use Pdf\Node\Table;
use Pdf\Node\TableCell;
use Pdf\Node\TableRow;
use Pdf\Style\Border;
use Pdf\Style\ColumnWidth;
use Pdf\Style\StylePatch;
use Pdf\Style\TextAlign;
use Pdf\Style\VerticalAlign;

/*
 * FPDF tutorial 5 plus the countries.txt dataset — pushed further:
 *
 *   - a spanning title row above the column headers
 *   - zebra striping via a per-row cell background
 *   - right-aligned, formatted numeric columns
 *   - a bold totals row
 *   - the header repeats automatically on every page the table spans
 */

$countries = array_map(
    static fn (string $line): array => explode(';', trim($line)),
    file(__DIR__ . '/data/countries.txt', FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) ?: [],
);

$navy = Color::rgb(22, 38, 74);
$stripe = Color::rgb(244, 247, 251);
$headerBg = Color::rgb(22, 38, 74);
$right = new StylePatch(align: TextAlign::Right);
$headCell = new StylePatch(bold: true, color: Color::white());

$rows = [
    new TableRow([
        new TableCell(
            'Countries of Europe',
            colspan: 4,
            patch: new StylePatch(bold: true, color: Color::white(), fontSizePt: 11.0),
            background: $navy,
        ),
    ]),
    new TableRow([
        new TableCell('Country', patch: $headCell, background: $headerBg),
        new TableCell('Capital', patch: $headCell, background: $headerBg),
        new TableCell('Area (km2)', verticalAlign: VerticalAlign::Bottom, patch: $headCell, background: $headerBg),
        new TableCell('Pop. (000)', verticalAlign: VerticalAlign::Bottom, patch: $headCell, background: $headerBg),
    ]),
];

// Repeat the dataset a few times so the table flows onto a second page and the
// header block (rows 1-2) repeats. One totals row closes it off.
$totalArea = 0;
$totalPop = 0;
$rowIndex = 0;
for ($repeat = 0; $repeat < 4; $repeat++) {
    foreach ($countries as [$country, $capital, $area, $pop]) {
        $totalArea += (int) $area;
        $totalPop += (int) $pop;
        $bg = $rowIndex++ % 2 === 1 ? $stripe : null;
        $rows[] = new TableRow([
            new TableCell($country, background: $bg),
            new TableCell($capital, background: $bg),
            new TableCell(number_format((int) $area), patch: $right, background: $bg),
            new TableCell(number_format((int) $pop), patch: $right, background: $bg),
        ]);
    }
}
$rows[] = new TableRow([
    new TableCell(number_format($rowIndex) . ' rows', colspan: 2, patch: new StylePatch(bold: true)),
    new TableCell(number_format($totalArea), patch: new StylePatch(bold: true, align: TextAlign::Right)),
    new TableCell(number_format($totalPop), patch: new StylePatch(bold: true, align: TextAlign::Right)),
]);
$flowRows = $rows;

Document::create()
    ->meta(fn ($m) => $m->title('Countries table')->subject('Automatic column sizing'))
    ->page(function ($p) use ($flowRows, $navy): void {
        $p->footer(fn (PageContext $c) => new Paragraph(
            "Countries table   –   page {$c->pageNumber} / {$c->pageCount}",
            new StylePatch(fontSizePt: 8.0, color: Color::gray(120), align: TextAlign::Center, spaceAfterPt: 0.0),
        ));

        $p->heading(1, 'Automatic column sizing', new StylePatch(color: $navy));
        $p->paragraph(
            'The two text columns take their width from their content; the two '
            . 'numeric columns are fixed. Column widths always sum to the '
            . 'available measure, and the header block repeats on every page.',
            new StylePatch(spaceAfterPt: 8.0),
        );

        $p->add(new Table(
            $flowRows,
            [
                ColumnWidth::auto(),
                ColumnWidth::auto(),
                ColumnWidth::fixed(78.0),
                ColumnWidth::fixed(78.0),
            ],
            headerRows: 2,
            borderColor: Color::gray(200),
            cellPaddingPt: new Edges(3.5, 6.0, 3.5, 6.0),
        ));
    })
    ->save(__DIR__ . '/table.pdf');

echo 'Wrote ' . __DIR__ . "/table.pdf\n";
