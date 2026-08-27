<?php

declare(strict_types=1);

namespace Pdf\Node;

use Pdf\Style\StylePatch;

/**
 * A block that groups children and can carry padding, a border and a
 * background. Ports the fill/border drawing of `Cell()` (fpdf.php:605-624) as a
 * reusable box rather than a one-shot cell.
 */
final readonly class Container implements BlockNode
{
    /** @var list<BlockNode> */
    public array $children;

    /** @param iterable<BlockNode> $children */
    public function __construct(
        iterable $children = [],
        private StylePatch $patch = new StylePatch(),
    ) {
        $this->children = is_array($children) ? array_values($children) : iterator_to_array($children, false);
    }

    public function patch(): StylePatch
    {
        return $this->patch;
    }
}
