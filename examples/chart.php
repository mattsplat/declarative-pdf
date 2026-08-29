<?php

declare(strict_types=1);

require __DIR__ . '/../vendor/autoload.php';

use Pdf\Chart\LegendPosition;
use Pdf\Chart\Series;
use Pdf\Color\Color;
use Pdf\Document;
use Pdf\Geometry\Unit;
use Pdf\Node\Chart;
use Pdf\Style\StylePatch;
use Pdf\Style\TextAlign;

$revenue = Series::of('Revenue', [32, 58, 41, 76]);
$cost = Series::of('Cost', [21, 30, 27, 44]);
$quarters = ['Q1', 'Q2', 'Q3', 'Q4'];

Document::create()
    ->meta(fn ($m) => $m->title('Charts'))
    ->page(function ($p) use ($revenue, $cost, $quarters): void {
        $p->units(Unit::Mm);

        $p->heading(1, 'Charts');
        $p->paragraph('A thin Chart node built on the Path primitive: bar, line, pie and sparkline, '
            . 'with a nice-number value axis, ticks, category labels and an optional legend.');

        $p->heading(2, 'Bar — two series with a legend');
        $p->chart(Chart::bar([$revenue, $cost], $quarters, 150, 55, legend: LegendPosition::Bottom));

        $p->heading(2, 'Line');
        $p->chart(Chart::line([Series::of('Sessions', [120, 145, 138, 172, 190, 168, 205])],
            ['Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat', 'Sun'], 150, 48));

        $p->heading(2, 'Pie');
        $p->chart(Chart::pie([45, 25, 18, 12], ['Direct', 'Search', 'Social', 'Referral'], 55));

        $p->heading(2, 'Sparkline — inline trend');
        $p->paragraph('Revenue, last eight weeks:', new StylePatch(spaceAfterPt: 2.0));
        $p->chart(Chart::sparkline([12, 15, 11, 19, 17, 22, 20, 26], 140, 20, Color::fromHex('#2f6fbf')));
    })
    ->save(__DIR__ . '/chart.pdf');

echo "Wrote " . __DIR__ . "/chart.pdf\n";
