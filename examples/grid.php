<?php

declare(strict_types=1);

require __DIR__ . '/../vendor/autoload.php';

use Pdf\Builder\PageBuilder;
use Pdf\Builder\Panel;
use Pdf\Color\Color;
use Pdf\Document;
use Pdf\Geometry\Fit;
use Pdf\Geometry\Orientation;
use Pdf\Geometry\PageSize;
use Pdf\Geometry\ShrinkMode;
use Pdf\Geometry\Unit;
use Pdf\Layout\Track;
use Pdf\Node\Paragraph;
use Pdf\Node\Path;
use Pdf\Style\Border;
use Pdf\Style\Paint;
use Pdf\Style\StylePatch;
use Pdf\Support\Source;

/*
 * The absolute-layout kit: carve the writable area into regions with a Grid,
 * frame each region with Panel, and rule the seams with hline()/vline() —
 * no hand-computed `$x + $w + $gutter` chains.
 *
 *   $p->grid(gutterPt: 14)
 *     -> rowTracks([Track::pt(52), Track::fr(1)])   // fixed banner + flexible body
 *     -> the body splits 2:1 into a main column and a sidebar
 *     -> the main column splits into a drawing panel and a notes panel
 *
 * Everything is in points (the Grid's native unit); Panel::in() takes the point
 * rectangles straight from the grid.
 */

// A stand-in "imported drawing": a schematic floor plan as its own PDF, then
// resolved through Source::first() so $GRID_DRAWING can point at a real one.
$drawing = sys_get_temp_dir() . '/grid-drawing.pdf';
$wall = new Paint(stroke: Color::gray(25), strokeWidthPt: 1.3);
Document::create()
    ->page(function (PageBuilder $p) use ($wall): void {
        $p->size(PageSize::fromUnits(12, 9, Unit::In))->orientation(Orientation::Landscape)
            ->units(Unit::In)->margin(0);
        $p->place(0.4, 0.4, 11.2, 8.2, [Path::rectangle(11.2, 8.2, $wall, Unit::In)], shrink: ShrinkMode::None);
        foreach ([[0.4, 0.4, 5, 5, 'LAB A'], [5.4, 0.4, 6.2, 3, 'LAB B'], [5.4, 3.4, 6.2, 2.2, 'MEETING'],
            [0.4, 5.4, 7, 3.2, 'OPEN PLAN'], [7.4, 5.6, 4.2, 3, 'PLANT']] as [$x, $y, $w, $h, $label]) {
            $p->place($x, $y, $w, $h, [Path::rectangle($w, $h, $wall, Unit::In)], shrink: ShrinkMode::None);
            $p->place($x + 0.2, $y + 0.2, $w - 0.4, 0.35, [
                new Paragraph($label, new StylePatch(bold: true, fontSizePt: 11.0, spaceAfterPt: 0.0)),
            ], shrink: ShrinkMode::None);
        }
    })
    ->save($drawing);

$drawingSource = Source::first([getenv('GRID_DRAWING') ?: null], static fn (): string => $drawing);

$rule = Color::gray(60);

Document::create()
    ->meta(fn ($m) => $m->title('Layout kit')->subject('Grid + Panel + hline/vline'))
    ->page(function (PageBuilder $p) use ($drawingSource, $rule): void {
        $p->size(PageSize::letter())->landscape()->units(Unit::Pt)->margin(28);

        [$banner, $body] = $p->grid(gutterPt: 14)->rowTracks([Track::pt(52), Track::fr(1)]);
        [$main, $sidebar] = $body->columns(2, 1);
        [$drawingCell, $notesCell] = $main->rows(3, 1);

        // Banner: a title, underlined across the full writable width.
        $b = $banner->rect();
        $p->place($b->x, $b->y, $b->width, $b->height, [
            new Paragraph('RIVERSIDE LABS — LEVEL 2 FIT-OUT', new StylePatch(bold: true, fontSizePt: 18.0)),
            new Paragraph('General arrangement · 1:100 · issued for coordination',
                new StylePatch(fontSizePt: 9.0, color: Color::gray(90), spaceAfterPt: 0.0)),
        ]);
        $p->hline($b->x, $b->bottom() + 7.0, $b->width, Border::uniform(1.0, $rule));

        // Seam between the main column and the sidebar.
        $seamX = $main->rect()->right() + 7.0;
        $p->vline($seamX, $body->rect()->y, $body->rect()->height, Border::uniform(0.5, $rule));

        Panel::in($drawingCell->rect())
            ->showing($drawingSource)
            ->fitted(Fit::Contain)
            ->drawOn($p);

        Panel::in($notesCell->rect())
            ->inset(10.0)
            ->containing([
                new Paragraph('NOTES', new StylePatch(bold: true, fontSizePt: 10.0)),
                new Paragraph('1. Do not scale from this drawing.', new StylePatch(fontSizePt: 8.5)),
                new Paragraph('2. Verify all dimensions on site.', new StylePatch(fontSizePt: 8.5)),
                new Paragraph('3. Services coordination to M&E package.',
                    new StylePatch(fontSizePt: 8.5, spaceAfterPt: 0.0)),
            ])
            ->drawOn($p);

        Panel::in($sidebar->rect())
            ->inset(10.0)
            ->framed(Border::uniform(0.75, $rule))
            ->containing([
                new Paragraph('DRAWING REGISTER', new StylePatch(bold: true, fontSizePt: 10.0)),
                new Paragraph('A-200  Demolition plan', new StylePatch(fontSizePt: 8.5)),
                new Paragraph('A-201  General arrangement', new StylePatch(fontSizePt: 8.5)),
                new Paragraph('A-202  Reflected ceiling plan', new StylePatch(fontSizePt: 8.5)),
                new Paragraph('A-203  Finishes plan', new StylePatch(fontSizePt: 8.5, spaceAfterPt: 0.0)),
            ])
            ->drawOn($p);
    })
    ->save(__DIR__ . '/grid.pdf');

@unlink($drawing);
echo 'Wrote ' . __DIR__ . "/grid.pdf\n";
