<?php

declare(strict_types=1);

namespace Pdf\Node;

/**
 * The visual style of a page-to-page transition — the `/S` entry of a PDF
 * `/Trans` dictionary (PDF 2.0, 12.4.4). {@see Transition} emits only the extra
 * keys a given style actually reads.
 */
enum TransitionStyle
{
    case Split;
    case Blinds;
    case Box;
    case Wipe;
    case Dissolve;
    case Glitter;
    case Fade;
    case Push;
    case Cover;
    case Uncover;
    case Fly;

    /** The `/S` token, without the leading slash. */
    public function pdfName(): string
    {
        return match ($this) {
            self::Split => 'Split',
            self::Blinds => 'Blinds',
            self::Box => 'Box',
            self::Wipe => 'Wipe',
            self::Dissolve => 'Dissolve',
            self::Glitter => 'Glitter',
            self::Fade => 'Fade',
            self::Push => 'Push',
            self::Cover => 'Cover',
            self::Uncover => 'Uncover',
            self::Fly => 'Fly',
        };
    }
}
