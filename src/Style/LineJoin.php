<?php

declare(strict_types=1);

namespace Pdf\Style;

/** The shape drawn where two stroked segments meet (`j` operand). */
enum LineJoin: int
{
    case Miter = 0;
    case Round = 1;
    case Bevel = 2;
}
