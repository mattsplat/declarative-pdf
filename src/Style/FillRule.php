<?php

declare(strict_types=1);

namespace Pdf\Style;

/**
 * How a self-intersecting path decides what is inside (PDF 32000-1 §8.5.3.3).
 */
enum FillRule
{
    case NonZero;
    case EvenOdd;

    /** The `*` the even-odd variant appends to `f` / `B` / `W`. */
    public function operatorSuffix(): string
    {
        return $this === self::EvenOdd ? '*' : '';
    }
}
