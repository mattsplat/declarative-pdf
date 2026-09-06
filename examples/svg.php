<?php

declare(strict_types=1);

require __DIR__ . '/../vendor/autoload.php';

use Pdf\Color\Color;
use Pdf\Document;
use Pdf\Geometry\Fit;
use Pdf\Node\Svg;
use Pdf\Style\Border;
use Pdf\Style\StylePatch;
use Pdf\Style\TextAlign;

/*
 * SVG import, drawn as real vector linework rather than rasterised — zoom the
 * output to 800% and the edges stay sharp.
 *
 *   1. block flow at intrinsic and stated sizes, and alignment
 *   2. absolute placement with a fit mode, and markup held in memory
 */

$badge = __DIR__ . '/data/badge.svg';
$glyph = __DIR__ . '/data/chart-glyph.svg';

Document::create()
    ->meta(fn ($m) => $m->title('SVG import'))

    ->page(function ($p) use ($badge, $glyph): void {
        $p->heading(1, 'SVG import');
        $p->paragraph(
            'Paths, basic shapes, groups, transforms, gradients and opacity are read '
            . 'straight into the drawing primitives — no raster step, no ext-gd.',
        );

        $p->heading(2, 'In block flow');
        $p->paragraph('At its intrinsic size, taken from the width/height attributes:');
        $p->svg($badge);

        $p->paragraph('At a stated width, centred — the whole drawing scales, strokes included:');
        $p->svg($badge, width: 45, align: TextAlign::Center);

        $p->paragraph('image() dispatches an .svg to the vector path automatically:');
        $p->image($glyph, width: 90);
    })

    ->page(function ($p) use ($badge, $glyph): void {
        $p->heading(2, 'From markup in memory');
        $p->paragraph('Svg::fromString() takes markup you have built or fetched yourself.');

        $p->add(Svg::fromString(
            '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 100 20">'
            . '<rect width="100" height="20" rx="10" fill="#0f766e"/>'
            . '<circle cx="10" cy="10" r="6" fill="#5eead4"/>'
            . '<circle cx="30" cy="10" r="6" fill="#5eead4" fill-opacity="0.7"/>'
            . '<circle cx="50" cy="10" r="6" fill="#5eead4" fill-opacity="0.45"/>'
            . '</svg>',
            width: 70,
        ));

        $p->paragraph(
            'Anything that would paint but cannot be reproduced — text, filters, masks, '
            . 'stylesheets — raises an SvgException rather than being silently dropped.',
            new StylePatch(fontSizePt: 9.0),
        );

        // Absolute placement: the frames show the rectangles being fitted into.
        $p->heading(2, 'Placed absolutely');
        $p->paragraph('placeSvg() fits a drawing into a rectangle exactly as placeImage() does.');

        $rule = Border::uniform(0.5, Color::fromHex('#94a3b8'));
        $p->frame(20, 120, 80, 50, $rule);
        $p->placeSvg(20, 120, 80, 50, $glyph, Fit::Contain);

        $p->frame(110, 120, 80, 50, $rule);
        $p->placeSvg(110, 120, 80, 50, $badge, Fit::Contain);
    })

    ->save(__DIR__ . '/svg.pdf');

echo 'Wrote ' . __DIR__ . "/svg.pdf\n";
