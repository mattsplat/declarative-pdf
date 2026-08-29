<?php

declare(strict_types=1);

namespace Pdf\Node;

use Pdf\Style\FillRule;
use Pdf\Style\StylePatch;

/**
 * Clips its children to the region of a {@see Path}. The path supplies only the
 * geometry — its paint is ignored; pair it with a sibling {@see Path} node to
 * draw the outline too.
 *
 * The clip occupies the path's box in block flow and never splits; anything a
 * child draws outside that region is masked away.
 */
final readonly class Clip implements BlockNode
{
    /** @var list<BlockNode> */
    public array $children;

    /** @param iterable<BlockNode> $children */
    public function __construct(
        public Path $path,
        iterable $children = [],
        public FillRule $clipRule = FillRule::NonZero,
        private StylePatch $patch = new StylePatch(),
    ) {
        $this->children = is_array($children) ? array_values($children) : iterator_to_array($children, false);
    }

    public function patch(): StylePatch
    {
        return $this->patch;
    }
}
