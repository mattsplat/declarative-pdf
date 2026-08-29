<?php

declare(strict_types=1);

require __DIR__ . '/../vendor/autoload.php';

use Pdf\Color\Color;
use Pdf\Document;
use Pdf\Geometry\Point;
use Pdf\Geometry\ShrinkMode;
use Pdf\Geometry\Unit;
use Pdf\Node\Paragraph;
use Pdf\Node\Path;
use Pdf\Style\FillRule;
use Pdf\Style\LineCap;
use Pdf\Style\LineJoin;
use Pdf\Style\Paint;
use Pdf\Style\StylePatch;
use Pdf\Style\TextAlign;

/** A five-pointed star, drawn as a pentagram so the even-odd rule hollows it out. */
$star = static function (float $radius): array {
    $points = [];
    for ($k = 0; $k < 5; $k++) {
        $angle = deg2rad($k * 144 - 90);
        $points[] = new Point($radius + $radius * cos($angle), $radius + $radius * sin($angle));
    }

    return $points;
};

$quarters = ['Q1' => 32.0, 'Q2' => 58.0, 'Q3' => 41.0, 'Q4' => 76.0];
$palette = [Color::fromHex('#2f6fbf'), Color::fromHex('#3f9d5a'), Color::fromHex('#d9803c'), Color::fromHex('#8a4fbf')];

Document::create()
    ->meta(fn ($m) => $m->title('Vector drawing'))
    ->page(function ($p) use ($star, $quarters, $palette) {
        $p->units(Unit::Mm);

        $p->heading(1, 'Vector drawing');
        $p->paragraph('A Path node is an ordered list of moveTo / lineTo / curveTo / close '
            . 'commands painted with a solid fill, a solid stroke, or both. It sits in normal '
            . 'block flow like any other node, and in an absolute place() area like any other node.');

        $p->heading(2, 'In block flow');
        $p->paragraph('Each shape stacks below the last, honouring spaceBefore / spaceAfter:');

        $p->path(Path::rectangle(60, 14, Paint::filled(Color::fromHex('#2f6fbf')), patch: new StylePatch(spaceAfterPt: 8.0)));
        $p->path(Path::ellipse(60, 18, new Paint(
            fill: Color::fromHex('#e8eef8'),
            stroke: Color::fromHex('#2f6fbf'),
            strokeWidthPt: 1.5,
        ), patch: new StylePatch(spaceAfterPt: 8.0)));
        $p->path(Path::line(0, 0, 170, 0, Paint::stroked(Color::gray(140), 1.0), patch: new StylePatch(spaceAfterPt: 10.0)));

        $p->heading(2, 'Absolutely placed');
        $p->paragraph('Placed shapes land in their own rectangle. Left to right: an even-odd '
            . 'pentagram, a non-zero one, a rounded-join triangle and a filled circle.');

        // Placed content sits outside the flow, so the flow reserves the row itself.
        $p->spacer(34);

        $row = 122.0;
        $p->place(20, $row, 30, 30, [
            Path::polygon($star(15.0), Paint::filled(Color::fromHex('#d9803c'), FillRule::EvenOdd)),
        ], shrink: ShrinkMode::None);
        $p->place(60, $row, 30, 30, [
            Path::polygon($star(15.0), Paint::filled(Color::fromHex('#d9803c'))),
        ], shrink: ShrinkMode::None);
        $p->place(100, $row, 30, 30, [
            Path::polygon(
                [new Point(15, 0), new Point(30, 28), new Point(0, 28)],
                new Paint(stroke: Color::fromHex('#3f9d5a'), strokeWidthPt: 3.0, lineJoin: LineJoin::Round),
            ),
        ], shrink: ShrinkMode::None);
        $p->place(140, $row, 30, 30, [
            Path::ellipse(30, 30, Paint::filled(Color::fromHex('#8a4fbf'))),
        ], shrink: ShrinkMode::None);

        $p->heading(2, 'A bar chart, hand-built');
        $p->paragraph('No chart layer yet — four filled rectangles, an axis line and four labels.');

        $chartX = 22.0;
        $chartTop = 180.0;
        $chartHeight = 55.0;
        $barWidth = 26.0;
        $gap = 14.0;
        $scaleMax = 80.0;

        foreach (array_keys($quarters) as $index => $label) {
            $value = $quarters[$label];
            $height = $chartHeight * $value / $scaleMax;
            $x = $chartX + $index * ($barWidth + $gap);

            $p->place($x, $chartTop + $chartHeight - $height, $barWidth, $height, [
                Path::rectangle($barWidth, $height, Paint::filled($palette[$index])),
            ], shrink: ShrinkMode::None);

            $p->place($x, $chartTop + $chartHeight + 2.0, $barWidth, 8.0, [
                new Paragraph($label, new StylePatch(fontSizePt: 9.0, align: TextAlign::Center, spaceAfterPt: 0.0)),
            ], shrink: ShrinkMode::None);
            $p->place($x, $chartTop + $chartHeight - $height - 7.0, $barWidth, 8.0, [
                new Paragraph((string) (int) $value, new StylePatch(
                    fontSizePt: 9.0,
                    color: Color::gray(90),
                    align: TextAlign::Center,
                    spaceAfterPt: 0.0,
                )),
            ], shrink: ShrinkMode::None);
        }

        $axisWidth = 4 * $barWidth + 3 * $gap;
        $p->place($chartX, $chartTop + $chartHeight, $axisWidth, 1.0, [
            Path::line(0, 0, $axisWidth, 0, Paint::stroked(Color::gray(60), 1.0, LineCap::Square)),
        ], shrink: ShrinkMode::None);
    })
    ->save(__DIR__ . '/shapes.pdf');

echo "Wrote " . __DIR__ . "/shapes.pdf\n";
