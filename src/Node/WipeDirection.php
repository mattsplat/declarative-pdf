<?php

declare(strict_types=1);

namespace Pdf\Node;

/**
 * The `/Di` value for a Wipe transition — the edge the wipe travels towards.
 * Wipe admits the four axis-aligned angles (PDF 2.0, Table 168).
 */
enum WipeDirection: int implements TransitionDi
{
    case Rightward = 0;
    case Upward = 90;
    case Leftward = 180;
    case Downward = 270;

    public function pdfValue(): string
    {
        return (string) $this->value;
    }
}
