<?php

declare(strict_types=1);

namespace Pdf\Chart;

/**
 * The four shapes a {@see \Pdf\Node\Chart} can take. Bar and line share a
 * cartesian frame (axes, ticks, category labels); pie is polar; a sparkline is
 * a bare trend line with no frame at all.
 */
enum ChartKind
{
    case Bar;
    case Line;
    case Pie;
    case Sparkline;

    /** Whether this kind draws a value axis, ticks and category labels. */
    public function isCartesian(): bool
    {
        return $this === self::Bar || $this === self::Line;
    }
}
