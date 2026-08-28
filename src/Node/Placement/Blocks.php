<?php

declare(strict_types=1);

namespace Pdf\Node\Placement;

use Pdf\Geometry\ShrinkMode;
use Pdf\Node\BlockNode;

/**
 * Block content that flows within an area's width. When it is taller than the
 * area, {@see ShrinkMode} decides how it is fitted: uniform geometric scale
 * (the default), a smaller effective font size that re-flows, or not at all.
 */
final readonly class Blocks implements PlacementContent
{
    /** @var list<BlockNode> */
    public array $blocks;

    /** @param iterable<BlockNode> $blocks */
    public function __construct(iterable $blocks, public ShrinkMode $shrink = ShrinkMode::Scale)
    {
        $this->blocks = is_array($blocks) ? array_values($blocks) : iterator_to_array($blocks, false);
    }
}
