<?php

declare(strict_types=1);

namespace Pdf\Node;

use Pdf\Style\StylePatch;

/**
 * A named destination. Place it before the content it should scroll to; an
 * inline link with target `#<name>` jumps here.
 *
 * Replaces `AddLink()` + `SetLink()` (fpdf.php:536, 544) — the position is
 * resolved automatically after layout.
 */
final readonly class Anchor implements BlockNode
{
    public function __construct(public string $name)
    {
    }

    public function patch(): StylePatch
    {
        return new StylePatch();
    }
}
