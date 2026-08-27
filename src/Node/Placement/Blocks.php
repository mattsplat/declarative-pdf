<?php

declare(strict_types=1);

namespace Pdf\Node\Placement;

use Pdf\Node\BlockNode;

/**
 * Block content that flows within an area's width and is scaled down uniformly
 * if it is taller than the area (shrink-to-fit).
 */
final readonly class Blocks implements PlacementContent
{
    /** @var list<BlockNode> */
    public array $blocks;

    /** @param iterable<BlockNode> $blocks */
    public function __construct(iterable $blocks)
    {
        $this->blocks = is_array($blocks) ? array_values($blocks) : iterator_to_array($blocks, false);
    }
}
