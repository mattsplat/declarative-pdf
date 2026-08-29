<?php

declare(strict_types=1);

namespace Pdf\Chart;

use Pdf\Color\Color;

/**
 * The default series colours, cycled by index so a chart with no explicit
 * {@see Series} colours still renders deterministically. Matches the swatches
 * `examples/shapes.php` hand-picked for its bar chart.
 */
final class Palette
{
    /** @var list<string> */
    private const HEX = ['#2f6fbf', '#3f9d5a', '#d9803c', '#8a4fbf', '#c0504d', '#4bacc6', '#9bbb59', '#f0a30a'];

    public static function color(int $index): Color
    {
        return Color::fromHex(self::HEX[$index % count(self::HEX)]);
    }
}
