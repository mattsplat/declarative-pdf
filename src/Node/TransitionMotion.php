<?php

declare(strict_types=1);

namespace Pdf\Node;

/**
 * The `/M` entry of a `/Trans` dictionary — whether a Split, Box or Fly
 * transition moves inward (from the edges to the centre) or outward.
 */
enum TransitionMotion
{
    case In;
    case Out;

    /** The `/M` token, without the leading slash. */
    public function pdfName(): string
    {
        return match ($this) {
            self::In => 'I',
            self::Out => 'O',
        };
    }
}
