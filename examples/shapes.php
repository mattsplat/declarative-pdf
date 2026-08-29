<?php

declare(strict_types=1);

require __DIR__ . '/../vendor/autoload.php';

use Pdf\Color\Color;
use Pdf\Document;
use Pdf\Geometry\PathCommand;
use Pdf\Geometry\Point;
use Pdf\Geometry\ShrinkMode;
use Pdf\Geometry\Unit;
use Pdf\Node\Clip;
use Pdf\Node\Paragraph;
use Pdf\Node\Path;
use Pdf\Style\FillRule;
use Pdf\Style\GradientStop;
use Pdf\Style\LinearGradient;
use Pdf\Style\LineCap;
use Pdf\Style\LineJoin;
use Pdf\Style\Paint;
use Pdf\Style\RadialGradient;
use Pdf\Style\StylePatch;
use Pdf\Style\TextAlign;

/*
 * The vector drawing layer, in three pages:
 *   1. primitives in block flow and in place() areas; fill rules; caps & joins
 *   2. gradient fills (axial + radial) and clipping to an arbitrary path
 *   3. a poster, composed from the above
 */

/** N-pointed star as a self-intersecting polygon (even-odd hollows the centre). */
$star = static function (float $r, int $points = 5): array {
    $out = [];
    $step = $points % 2 === 0 ? 2 : ($points === 5 ? 2 : 1);
    for ($k = 0; $k < $points; $k++) {
        $a = deg2rad(($k * 360 * $step / $points) - 90);
        $out[] = new Point($r + $r * cos($a), $r + $r * sin($a));
    }

    return $out;
};

$sunset = [
    GradientStop::at(0.0, Color::fromHex('#f9d976')),
    GradientStop::at(0.55, Color::fromHex('#e96443')),
    GradientStop::at(1.0, Color::fromHex('#6a1b4d')),
];
$sky = [
    GradientStop::at(0.0, Color::fromHex('#e6f2ff')),
    GradientStop::at(1.0, Color::fromHex('#2f6fbf')),
];

Document::create()
    ->meta(fn ($m) => $m->title('Vector drawing')->subject('Path, gradients, clipping'))

    // --- page 1: primitives ------------------------------------------------
    ->page(function ($p) use ($star): void {
        $p->units(Unit::Mm);
        $p->heading(1, 'Primitives');
        $p->paragraph('A Path is an ordered moveTo / lineTo / curveTo / close list '
            . 'painted with a fill, a stroke, or both. It flows like any block and '
            . 'places like any block.');

        $p->heading(2, 'In block flow');
        $p->path(Path::rectangle(120, 12, Paint::filled(Color::fromHex('#2f6fbf')),
            patch: new StylePatch(spaceAfterPt: 6.0)));
        $p->path(Path::ellipse(120, 16, new Paint(
            fill: Color::fromHex('#e8eef8'),
            stroke: Color::fromHex('#2f6fbf'),
            strokeWidthPt: 1.5,
        ), patch: new StylePatch(spaceAfterPt: 6.0)));
        $p->path(Path::line(0, 0, 170, 0, Paint::stroked(Color::gray(150), 1.0),
            patch: new StylePatch(spaceAfterPt: 8.0)));

        $p->heading(2, 'Fill rules');
        $p->paragraph('The same pentagram, non-zero (solid) then even-odd (hollow centre):');
        $p->spacer(30);
        $p->place(24, 100, 28, 28, [Path::polygon($star(14.0), Paint::filled(Color::fromHex('#d9803c')))],
            shrink: ShrinkMode::None);
        $p->place(62, 100, 28, 28, [Path::polygon($star(14.0), Paint::filled(Color::fromHex('#d9803c'), FillRule::EvenOdd))],
            shrink: ShrinkMode::None);

        $p->heading(2, 'Caps and joins', new StylePatch(spaceBeforePt: 10.0));
        $p->paragraph('Butt / round / square caps; miter / round / bevel joins:');
        $p->spacer(26);
        $y = 148.0;
        foreach ([LineCap::Butt, LineCap::Round, LineCap::Square] as $i => $cap) {
            $p->place(24 + $i * 24, $y, 22, 8, [
                Path::of(
                    [PathCommand::moveTo(1, 4), PathCommand::lineTo(17, 4)],
                    22,
                    8,
                    Paint::stroked(Color::gray(60), 6.0, $cap),
                ),
            ], shrink: ShrinkMode::None);
        }
        foreach ([LineJoin::Miter, LineJoin::Round, LineJoin::Bevel] as $i => $join) {
            $p->place(24 + $i * 30, $y + 16, 28, 18, [
                Path::of(
                    [PathCommand::moveTo(2, 16), PathCommand::lineTo(14, 2), PathCommand::lineTo(26, 16)],
                    28,
                    18,
                    new Paint(stroke: Color::fromHex('#3f9d5a'), strokeWidthPt: 6.0, lineJoin: $join),
                ),
            ], shrink: ShrinkMode::None);
        }
    })

    // --- page 2: gradients + clipping ------------------------------------
    ->page(function ($p) use ($star, $sunset, $sky): void {
        $p->units(Unit::Mm);
        $p->heading(1, 'Gradients and clipping');
        $p->paragraph('A gradient fills a Path with a PDF shading -- axial or radial. '
            . 'Stops and geometry are fractions of the shape\'s box.');

        $p->path(Path::rectangle(150, 24, Paint::gradient(LinearGradient::horizontal($sunset)),
            patch: new StylePatch(spaceAfterPt: 6.0)));
        $p->path(Path::ellipse(150, 24, Paint::gradient(RadialGradient::centered($sky)),
            patch: new StylePatch(spaceAfterPt: 6.0)));
        $p->path(Path::rectangle(150, 24, Paint::gradient(LinearGradient::between($sunset, 0.0, 0.0, 1.0, 1.0)),
            patch: new StylePatch(spaceAfterPt: 10.0)));

        $p->heading(2, 'Clipped to a shape');
        $p->paragraph('clip() masks its children to a Path region. Here a headline '
            . 'and a gradient panel are cut to a six-pointed star.');
        $p->spacer(6);
        $p->clip(
            Path::polygon($star(30.0, 6), Paint::filled(Color::black())),
            [
                new Paragraph('CLIP', new StylePatch(
                    fontSizePt: 30.0, bold: true, color: Color::white(), align: TextAlign::Center,
                )),
                Path::rectangle(60, 46, Paint::gradient(LinearGradient::vertical($sunset))),
            ],
            FillRule::EvenOdd,
        );
    })

    // --- page 3: a poster --------------------------------------------------
    ->page(function ($p) use ($star, $sunset): void {
        $p->units(Unit::Mm);
        $p->margin(0);

        // full-bleed gradient ground
        $p->place(0, 0, 210, 297, [Path::rectangle(210, 297, Paint::gradient(
            LinearGradient::vertical([
                GradientStop::at(0.0, Color::fromHex('#10203a')),
                GradientStop::at(1.0, Color::fromHex('#3a1030')),
            ]),
        ))], shrink: ShrinkMode::None);

        // a ring of stars
        for ($k = 0; $k < 8; $k++) {
            $a = deg2rad($k * 45);
            $cx = 105 + 68 * cos($a) - 6;
            $cy = 150 + 68 * sin($a) - 6;
            $p->place($cx, $cy, 12, 12, [
                Path::polygon($star(6.0), Paint::filled(Color::fromHex('#f9d976'))),
            ], shrink: ShrinkMode::None);
        }

        // a gradient band clipped to a chevron, placed absolutely
        $p->place(30, 96, 150, 30, [
            new Clip(
                Path::polygon([
                    new Point(0, 0), new Point(150, 0), new Point(150, 20),
                    new Point(75, 30), new Point(0, 20),
                ], Paint::filled(Color::black())),
                [Path::rectangle(150, 30, Paint::gradient(LinearGradient::horizontal($sunset)))],
            ),
        ], shrink: ShrinkMode::None);

        $p->place(20, 118, 170, 64, [
            new Paragraph('VECTOR', new StylePatch(fontSizePt: 54.0, bold: true, color: Color::white(), align: TextAlign::Center)),
            new Paragraph('drawn, filled, gradient-shaded and clipped', new StylePatch(
                fontSizePt: 12.0, color: Color::fromHex('#f9d976'), align: TextAlign::Center,
            )),
        ], shrink: ShrinkMode::None);
    })

    ->save(__DIR__ . '/shapes.pdf');

echo 'Wrote ' . __DIR__ . "/shapes.pdf\n";
