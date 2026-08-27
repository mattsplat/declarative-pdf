<?php

declare(strict_types=1);

namespace Pdf\Node;

use Pdf\Style\StylePatch;

/**
 * A block-level node in the document tree.
 *
 * Blocks stack vertically. Concrete types are a closed set; the style
 * resolver, measurer and renderer dispatch on the concrete class.
 */
interface BlockNode
{
    /** Style overrides this block contributes to itself and its subtree. */
    public function patch(): StylePatch;
}
