<?php

declare(strict_types=1);

require __DIR__ . '/../vendor/autoload.php';

use Pdf\Chart\LegendPosition;
use Pdf\Chart\Series;
use Pdf\Color\Color;
use Pdf\Document;
use Pdf\Geometry\Unit;
use Pdf\Layout\PageContext;
use Pdf\Node\Chart;
use Pdf\Node\Paragraph;
use Pdf\Node\Table;
use Pdf\Node\TableCell;
use Pdf\Node\TableRow;
use Pdf\Style\ColumnWidth;
use Pdf\Style\StylePatch;
use Pdf\Style\TextAlign;

/*
 * A one-page analytics dashboard drawn entirely from the Chart node — itself
 * a thin layer over the Path primitive. Bar, line, pie and inline sparklines,
 * a nice-number value axis, category labels and legends. Deterministic; no
 * dependencies.
 */

$blue = Color::fromHex('#2f6fbf');
$green = Color::fromHex('#3f9d5a');
$amber = Color::fromHex('#d9803c');
$ink = Color::rgb(22, 28, 40);
$muted = Color::gray(120);

/** One KPI cell: label, big figure, sparkline. */
$kpiCell = static fn (string $label, string $value, array $spark, Color $colour): TableCell => new TableCell([
    new Paragraph($label, new StylePatch(fontSizePt: 8.5, color: $muted, spaceAfterPt: 1.0)),
    new Paragraph($value, new StylePatch(fontSizePt: 19.0, bold: true, spaceAfterPt: 3.0)),
    Chart::sparkline($spark, 120.0, 15.0, $colour),
]);

Document::create()
    ->meta(fn ($m) => $m->title('Analytics dashboard')->subject('Chart node showcase'))
    ->page(function ($p) use ($blue, $green, $amber, $ink, $muted, $kpiCell): void {
        $p->units(Unit::Mm);

        $p->header(fn (PageContext $c) => new Paragraph(
            'WEEKLY DASHBOARD   ' . date('Y-m-d'),
            new StylePatch(fontSizePt: 7.5, color: $muted, spaceAfterPt: 0.0),
        ));

        $p->heading(1, 'This week', new StylePatch(color: $ink));

        // KPI strip: a borderless one-row table, three equal cells.
        $p->add(new Table(
            [
                new TableRow([
                    $kpiCell('Sessions', '48.2k', [30, 34, 31, 40, 44, 41, 48], $blue),
                    $kpiCell('Signups', '1,204', [120, 150, 138, 172, 165, 190, 205], $green),
                    $kpiCell('Churn', '2.1%', [3.0, 2.8, 2.6, 2.5, 2.4, 2.2, 2.1], $amber),
                ]),
            ],
            [ColumnWidth::fraction(1.0), ColumnWidth::fraction(1.0), ColumnWidth::fraction(1.0)],
            borderWidthPt: 0.0,
        ));

        $p->heading(2, 'Revenue vs. cost', new StylePatch(spaceBeforePt: 8.0));
        $p->chart(Chart::bar(
            [
                Series::of('Revenue', [32, 58, 41, 76, 69, 88], $blue),
                Series::of('Cost', [21, 30, 27, 44, 40, 47], Color::rgb(160, 190, 225)),
            ],
            ['W1', 'W2', 'W3', 'W4', 'W5', 'W6'],
            160.0,
            52.0,
            Unit::Mm,
            LegendPosition::Bottom,
        ));

        $p->heading(2, 'Sessions by day');
        $p->chart(Chart::line(
            [
                Series::of('This week', [120, 145, 138, 172, 190, 168, 205], $blue),
                Series::of('Last week', [110, 128, 132, 150, 162, 155, 170], Color::gray(170)),
            ],
            ['Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat', 'Sun'],
            160.0,
            48.0,
            Unit::Mm,
            LegendPosition::Bottom,
        ));

        $p->heading(2, 'Acquisition mix');
        $p->chart(Chart::pie(
            [45, 25, 18, 12],
            ['Direct', 'Search', 'Social', 'Referral'],
            52.0,
            Unit::Mm,
            LegendPosition::Right,
        ));

        $p->paragraph(
            'A bar axis always includes zero; a line axis fits its data. Series '
            . 'colours left null come from a fixed palette by position, so the '
            . 'same data always renders the same bytes.',
            new StylePatch(fontSizePt: 8.5, color: $muted, align: TextAlign::Justify, spaceBeforePt: 6.0),
        );
    })
    ->save(__DIR__ . '/chart.pdf');

echo 'Wrote ' . __DIR__ . "/chart.pdf\n";
