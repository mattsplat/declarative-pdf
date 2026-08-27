<?php

declare(strict_types=1);

namespace Pdf\Node;

use Pdf\Style\StylePatch;

/**
 * Forces the following content onto a new page. Replaces a manual `AddPage()`
 * call mid-flow (fpdf.php:287).
 */
final readonly class PageBreak implements BlockNode
{
    public function patch(): StylePatch
    {
        return new StylePatch();
    }
}
