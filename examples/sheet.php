<?php

declare(strict_types=1);

require __DIR__ . '/../vendor/autoload.php';

use Pdf\Color\Color;
use Pdf\Document;
use Pdf\Geometry\BoxAlign;
use Pdf\Geometry\Fit;
use Pdf\Geometry\Orientation;
use Pdf\Geometry\PageSize;
use Pdf\Geometry\PathCommand;
use Pdf\Geometry\Point;
use Pdf\Geometry\ShrinkMode;
use Pdf\Geometry\Unit;
use Pdf\Node\Paragraph;
use Pdf\Node\Path;
use Pdf\Node\Rule;
use Pdf\Node\Table;
use Pdf\Node\TableCell;
use Pdf\Node\TableRow;
use Pdf\Style\Border;
use Pdf\Style\ColumnWidth;
use Pdf\Style\Paint;
use Pdf\Style\StylePatch;
use Pdf\Style\TextAlign;

/*
 * An ARCH D sheet (24 x 36") laid out entirely in absolute areas, working in
 * inches: frame() for the border grid, placePdf() to drop an imported drawing
 * in as crisp vectors, place() for every text block, and Path for the north
 * arrow and the graphic scale bar.
 */

$fixtures = dirname(__DIR__) . '/tests/fixtures';
$ink = Color::gray(30);

// A stand-in for an imported CAD drawing: a schematic floor plan built from
// Path rectangles, generated as its own document and imported below.
$drawing = sys_get_temp_dir() . '/sheet-drawing.pdf';
$wall = new Paint(stroke: Color::gray(20), strokeWidthPt: 1.4);
Document::create()
    ->page(function ($p) use ($wall): void {
        $p->size(PageSize::fromUnits(24, 18, Unit::In))->orientation(Orientation::Landscape)
            ->units(Unit::In)->margin(0);

        $p->place(1, 1, 22, 16, [Path::rectangle(22, 16,
            new Paint(stroke: Color::gray(20), strokeWidthPt: 2.6), Unit::In)], shrink: ShrinkMode::None);

        $cells = [
            [1, 1, 8, 9, 'ENTRANCE HALL'], [9, 1, 7, 5, 'STUDIO 1'], [16, 1, 7, 5, 'STUDIO 2'],
            [9, 6, 14, 4, 'GALLERY'], [1, 10, 6, 7, 'OFFICE'], [7, 10, 9, 7, 'WORKSHOP'],
            [16, 10, 7, 7, 'STORE'],
        ];
        foreach ($cells as [$x, $y, $w, $h, $label]) {
            $p->place($x, $y, $w, $h, [Path::rectangle($w, $h, $wall, Unit::In)], shrink: ShrinkMode::None);
            $p->place($x + 0.25, $y + 0.25, $w - 0.5, 0.4, [
                new Paragraph($label, new StylePatch(fontSizePt: 12.0, bold: true, spaceAfterPt: 0.0)),
            ], shrink: ShrinkMode::None);
        }
        $p->place(2, 17.2, 20, 0.4, [
            new Paragraph('LEVEL 1 GENERAL ARRANGEMENT   1:100',
                new StylePatch(fontSizePt: 10.0, spaceAfterPt: 0.0)),
        ], shrink: ShrinkMode::None);
    })
    ->save($drawing);

$bold8 = new StylePatch(bold: true, fontSizePt: 8.0);

$titleBlock = new Table([
    new TableRow([new TableCell('PROJECT', patch: $bold8), new TableCell('Riverside Community Centre')]),
    new TableRow([new TableCell('CLIENT', patch: $bold8), new TableCell('Riverside Borough Council')]),
    new TableRow([new TableCell('DRAWING', patch: $bold8), new TableCell('Level 1 general arrangement')]),
    new TableRow([new TableCell('SHEET', patch: $bold8), new TableCell('A-101   of   A-140')]),
    new TableRow([new TableCell('SCALE', patch: $bold8), new TableCell('1 : 100 @ ARCH D')]),
    new TableRow([new TableCell('DATE', patch: $bold8), new TableCell(date('Y-m-d'))]),
    new TableRow([new TableCell('DRAWN', patch: $bold8), new TableCell('MJC')]),
], borderColor: Color::gray(70));

$revisions = new Table([
    new TableRow([
        new TableCell('REV', patch: $bold8),
        new TableCell('DATE', patch: $bold8),
        new TableCell('DESCRIPTION', patch: $bold8),
    ]),
    new TableRow([new TableCell('A'), new TableCell('2026-06-02'), new TableCell('Issued for coordination')]),
    new TableRow([new TableCell('B'), new TableCell('2026-07-14'), new TableCell('Stair core revised')]),
    new TableRow([new TableCell('C'), new TableCell('2026-08-20'), new TableCell('Issued for construction')]),
], [ColumnWidth::fixed(30.0), ColumnWidth::fixed(80.0), ColumnWidth::fraction(1.0)], headerRows: 1, borderColor: Color::gray(70));

// A north arrow: a solid half and a hollow half of the same triangle.
$northArrow = Path::of([
    PathCommand::moveTo(15, 0), PathCommand::lineTo(24, 34), PathCommand::lineTo(15, 26), PathCommand::close(),
    PathCommand::moveTo(15, 0), PathCommand::lineTo(6, 34), PathCommand::lineTo(15, 26), PathCommand::close(),
], 30, 34, new Paint(fill: Color::gray(30), stroke: Color::gray(30), strokeWidthPt: 0.8), Unit::Pt);

Document::create()
    ->meta(fn ($m) => $m->title('Drawing sheet')->subject('Absolute-area layout on ARCH D'))
    ->page(function ($p) use ($fixtures, $drawing, $ink, $titleBlock, $revisions, $northArrow): void {
        $p->size(PageSize::arch('d'))->landscape()->units(Unit::In)->margin(0);

        // Sheet border and the drawing viewport.
        $p->frame(0.5, 0.5, 35.0, 23.0, Border::uniform(1.5, $ink));
        $p->frame(1.0, 1.0, 25.5, 21.0, Border::uniform(0.5, Color::gray(120)));
        $p->placePdf(1.0, 1.0, 25.5, 21.0, $drawing, 1, Fit::Contain);

        // Right column: notes.
        $p->place(27.2, 1.0, 7.3, 9.5, [
            new Paragraph('GENERAL NOTES', new StylePatch(bold: true, fontSizePt: 10.0)),
            new Rule(0.75),
            new Paragraph('1. Do not scale from this drawing.'),
            new Paragraph('2. All dimensions in millimetres unless noted.'),
            new Paragraph('3. Verify all dimensions on site before fabrication.'),
            new Paragraph('4. Refer to structural drawings for grid and levels.'),
            new Paragraph('5. Fire strategy to consultant\'s report, rev. C.'),
        ], BoxAlign::TopLeft);

        // North arrow + a graphic scale bar.
        $p->place(27.4, 11.0, 0.45, 0.5, [$northArrow], shrink: ShrinkMode::None);
        $p->place(28.1, 11.15, 3.0, 0.4, [
            new Paragraph('TRUE NORTH', new StylePatch(fontSizePt: 8.0, spaceAfterPt: 0.0)),
        ], shrink: ShrinkMode::None);

        // graphic scale: four 1" segments, alternating fill
        for ($i = 0; $i < 4; $i++) {
            $fill = $i % 2 === 0 ? Color::gray(30) : Color::white();
            $p->place(27.2 + $i * 1.0, 12.4, 1.0, 0.18, [
                Path::rectangle(1.0, 0.18, new Paint(fill: $fill, stroke: Color::gray(30), strokeWidthPt: 0.5), Unit::In),
            ], shrink: ShrinkMode::None);
        }
        $p->place(27.2, 12.65, 4.5, 0.3, [
            new Paragraph('0        2        4        6        8 m   (1:100)',
                new StylePatch(fontSizePt: 6.5, spaceAfterPt: 0.0)),
        ], shrink: ShrinkMode::None);

        // Revision history, mid-right.
        $p->place(27.2, 13.6, 7.3, 4.0, [
            new Paragraph('REVISIONS', new StylePatch(bold: true, fontSizePt: 9.0, spaceAfterPt: 3.0)),
            $revisions,
        ], BoxAlign::TopLeft);

        // A small logo inside the bottom-right of the drawing area.
        $p->placeImage(23.2, 20.3, 2.2, 1.3, "{$fixtures}/dot-rgba.png", Fit::Contain, BoxAlign::BottomRight);

        // Title block, bottom-right corner.
        $p->place(27.2, 18.0, 7.3, 4.6, [$titleBlock], BoxAlign::BottomLeft);
    })
    ->save(__DIR__ . '/sheet.pdf');

@unlink($drawing);
echo 'Wrote ' . __DIR__ . "/sheet.pdf\n";
