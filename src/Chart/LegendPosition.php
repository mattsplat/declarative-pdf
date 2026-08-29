<?php

declare(strict_types=1);

namespace Pdf\Chart;

/**
 * Where a {@see \Pdf\Node\Chart}'s legend sits relative to the plot area, or
 * {@see self::None} to omit it. Top / Bottom lay the entries out in a centred
 * row; Right stacks them.
 */
enum LegendPosition
{
    case None;
    case Top;
    case Bottom;
    case Right;
}
