<?php

declare(strict_types=1);

require __DIR__ . '/../vendor/autoload.php';

use Pdf\Color\Color;
use Pdf\Document;
use Pdf\Geometry\BoxAlign;
use Pdf\Geometry\Fit;
use Pdf\Geometry\PageSize;
use Pdf\Geometry\Unit;
use Pdf\Node\Paragraph;
use Pdf\Node\Rule;
use Pdf\Node\Table;
use Pdf\Node\TableCell;
use Pdf\Node\TableRow;
use Pdf\Style\Border;
use Pdf\Style\StylePatch;
use Pdf\Style\TextAlign;

$fixtures = dirname(__DIR__) . '/tests/fixtures';

// A "drawing" produced elsewhere, that we will import onto the sheet as vectors.
$drawing = sys_get_temp_dir() . '/sheet-drawing.pdf';
Document::create()
    ->page(fn ($p) => $p
        ->size(PageSize::fromUnits(20, 16, Unit::In))
        ->heading(1, 'FLOOR PLAN — LEVEL 1')
        ->paragraph('(this stands in for an imported CAD drawing; it is placed as a '
            . 'vector Form XObject and stays crisp at any zoom)')
        ->paragraph(str_repeat('room ', 60)))
    ->save($drawing);

// An ARCH D sheet (24 x 36") laid out in areas, working in inches.
Document::create()
    ->meta(fn ($m) => $m->title('Drawing sheet'))
    ->page(function ($p) use ($fixtures, $drawing) {
        $p->size(PageSize::arch('d'))->landscape()->units(Unit::In)->margin(0);

        // Sheet border and the drawing viewport.
        $p->frame(0.5, 0.5, 35.0, 23.0, Border::uniform(1.5, Color::gray(40)));
        $p->frame(1.0, 1.0, 26.0, 21.0, Border::uniform(0.5, Color::gray(120)));
        $p->placePdf(1.0, 1.0, 26.0, 21.0, $drawing, 1, Fit::Contain);

        // Right column: notes + a small logo.
        $p->place(27.5, 1.0, 7.0, 14.0, [
            new Paragraph('GENERAL NOTES', new StylePatch(bold: true, fontSizePt: 11)),
            new Rule(0.75),
            new Paragraph('1. All dimensions in millimetres unless noted.'),
            new Paragraph('2. Verify all dimensions on site before fabrication.'),
            new Paragraph('3. This drawing is diagrammatic and not to scale.'),
        ], BoxAlign::TopLeft);

        $p->placeImage(31.0, 15.5, 3.5, 2.0, "{$fixtures}/dot-rgba.png", Fit::Contain, BoxAlign::TopRight);

        // Title block, bottom-right.
        $p->place(20.0, 18.5, 14.5, 4.0, [
            new Table([
                new TableRow([
                    new TableCell('PROJECT', patch: new StylePatch(bold: true, fontSizePt: 8)),
                    new TableCell('Riverside Community Centre'),
                ]),
                new TableRow([
                    new TableCell('SHEET', patch: new StylePatch(bold: true, fontSizePt: 8)),
                    new TableCell('A-101', patch: new StylePatch(align: TextAlign::Left)),
                ]),
                new TableRow([
                    new TableCell('SCALE', patch: new StylePatch(bold: true, fontSizePt: 8)),
                    new TableCell('1 : 100'),
                ]),
                new TableRow([
                    new TableCell('DATE', patch: new StylePatch(bold: true, fontSizePt: 8)),
                    new TableCell(date('Y-m-d')),
                ]),
            ], borderColor: Color::gray(60)),
        ], BoxAlign::BottomLeft);
    })
    ->save(__DIR__ . '/sheet.pdf');

@unlink($drawing);
echo "Wrote " . __DIR__ . "/sheet.pdf\n";
