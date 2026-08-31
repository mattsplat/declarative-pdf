<?php

declare(strict_types=1);

namespace Pdf\Node;

/**
 * The `/Dm` entry of a `/Trans` dictionary — whether a Split or Blinds
 * transition is carved horizontally or vertically.
 */
enum TransitionAxis
{
    case Horizontal;
    case Vertical;

    /** The `/Dm` token, without the leading slash. */
    public function pdfName(): string
    {
        return match ($this) {
            self::Horizontal => 'H',
            self::Vertical => 'V',
        };
    }
}
