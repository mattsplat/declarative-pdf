<?php

declare(strict_types=1);

namespace Pdf\Node;

use Pdf\Style\StylePatch;

/**
 * A logical page: a {@see PageMaster}, a stack of flow content, and optionally
 * a set of absolutely-positioned {@see Placement} areas.
 *
 * Flow content paginates across as many physical sheets as it needs;
 * placements render on the first sheet, over the flow.
 */
final readonly class Page
{
    /** @var list<BlockNode> */
    public array $children;

    /** @var list<Placement> */
    public array $placements;

    /**
     * @param iterable<BlockNode>  $children
     * @param iterable<Placement>  $placements
     */
    public function __construct(
        public PageMaster $master = new PageMaster(),
        iterable $children = [],
        public StylePatch $patch = new StylePatch(),
        iterable $placements = [],
    ) {
        $this->children = is_array($children) ? array_values($children) : iterator_to_array($children, false);
        $this->placements = is_array($placements) ? array_values($placements) : iterator_to_array($placements, false);
    }
}
