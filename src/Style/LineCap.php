<?php

declare(strict_types=1);

namespace Pdf\Style;

/** The shape drawn at the ends of an open stroked subpath (`J` operand). */
enum LineCap: int
{
    case Butt = 0;
    case Round = 1;
    case Square = 2;
}
