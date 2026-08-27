<?php

declare(strict_types=1);

require __DIR__ . '/../vendor/autoload.php';

use Pdf\Color\Color;
use Pdf\Document;
use Pdf\Layout\PageContext;
use Pdf\Node\Paragraph;
use Pdf\Node\Table;
use Pdf\Node\TableCell;
use Pdf\Node\TableRow;
use Pdf\Style\ColumnWidth;
use Pdf\Style\StylePatch;
use Pdf\Style\TextAlign;
use Pdf\Style\VerticalAlign;

// Port of FPDF tutorial 5 + the countries.txt dataset, repeated so the
// table flows across several pages with a repeating header.
$countries = array_map(
    static fn (string $line) => explode(';', trim($line)),
    file(__DIR__ . '/data/countries.txt', FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) ?: [],
);

$rows = [
    new TableRow([
        new TableCell('Country'),
        new TableCell('Capital'),
        new TableCell('Area (km2)', verticalAlign: VerticalAlign::Bottom),
        new TableCell('Pop. (thousands)', verticalAlign: VerticalAlign::Bottom),
    ]),
];
for ($repeat = 0; $repeat < 6; $repeat++) {
    foreach ($countries as [$country, $capital, $area, $pop]) {
        $rows[] = new TableRow([
            new TableCell($country),
            new TableCell($capital),
            new TableCell(number_format((int) $area), patch: new StylePatch(align: TextAlign::Right)),
            new TableCell(number_format((int) $pop), patch: new StylePatch(align: TextAlign::Right)),
        ]);
    }
}

Document::create()
    ->meta(fn ($m) => $m->title('Countries table'))
    ->page(function ($p) use ($rows) {
        $p->footer(fn (PageContext $c) => new Paragraph(
            "Page {$c->pageNumber} of {$c->pageCount}",
            new StylePatch(fontSizePt: 9.0, color: Color::gray(120), align: TextAlign::Center, spaceAfterPt: 0.0),
        ));

        $p->heading(1, 'Countries of Europe');
        $p->add(new Table(
            $rows,
            [
                ColumnWidth::auto(),
                ColumnWidth::auto(),
                ColumnWidth::fixed(90.0),
                ColumnWidth::fixed(100.0),
            ],
            headerRows: 1,
            repeatHeader: true,
            headerBackground: Color::rgb(230, 236, 245),
        ));
    })
    ->save(__DIR__ . '/table.pdf');

echo "Wrote " . __DIR__ . "/table.pdf\n";
