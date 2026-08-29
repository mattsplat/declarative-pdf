<?php

declare(strict_types=1);

namespace Pdf\Node;

use Pdf\Style\StylePatch;

/**
 * A reusable block that expands to other blocks.
 *
 * Subclass it, take your parameters in the constructor, and return the tree
 * from {@see self::body()}. A component composes anywhere a {@see BlockNode}
 * does — page flow, a {@see Container}, a {@see TableCell}, an absolute
 * placement — and is unit-testable without rendering (assert on what `body()`
 * returns).
 *
 * `body()` must be pure: the layout engine calls it more than once per render
 * (intrinsic sizing, each pagination pass), so build an equivalent tree each
 * time. A component whose `body()` reaches itself raises a
 * {@see \Pdf\Exception\LayoutException} rather than recursing forever.
 */
abstract readonly class Component implements BlockNode
{
    /** @return BlockNode|iterable<BlockNode> */
    abstract public function body(): BlockNode|iterable;

    /**
     * Style this component contributes to its whole body — padding, border,
     * background, or inherited properties (font, colour) the body can still
     * override. Applied exactly as if `body()` were wrapped in a
     * {@see Container} carrying this patch.
     */
    public function patch(): StylePatch
    {
        return new StylePatch();
    }
}
