<?php

declare(strict_types=1);

namespace Pdf\Render;

use Pdf\Node\Bookmark;

/**
 * Resolves a flat, level-tagged {@see Bookmark} list into an outline hierarchy:
 * a parent, an ordered child list and a descendant count per item, plus the
 * top-level sequence.
 *
 * Document order is preserved. An item nests under the nearest preceding item
 * of a lower level; a level that skips more than one step past the current
 * parent is clamped to parent + 1. Counts are the total number of descendants
 * (every level), which is what `/Count` needs while all items are open.
 */
final readonly class OutlineTree
{
    /** @var list<Bookmark> */
    public array $items;

    /** @var list<int> parent index per item, -1 for a top-level item */
    public array $parents;

    /** @var list<list<int>> ordered child indices per item */
    public array $children;

    /** @var list<int> top-level item indices, in document order */
    public array $roots;

    /** @var list<int> descendant count per item, all levels */
    public array $counts;

    /** @param list<Bookmark> $bookmarks */
    public function __construct(array $bookmarks)
    {
        $items = $bookmarks;
        $count = count($items);

        /** @var list<int> $parents */
        $parents = [];
        /** @var list<list<int>> $children */
        $children = array_fill(0, $count, []);
        /** @var list<int> $roots */
        $roots = [];
        /** @var list<int> $stack  item index at each open depth */
        $stack = [];

        foreach ($items as $i => $bookmark) {
            $depth = min(max($bookmark->level, 0), count($stack));
            $stack = array_slice($stack, 0, $depth);

            if ($stack === []) {
                $parents[$i] = -1;
                $roots[] = $i;
            } else {
                $parent = $stack[count($stack) - 1];
                $parents[$i] = $parent;
                $children[$parent][] = $i;
            }

            $stack[] = $i;
        }

        /** @var list<int> $counts */
        $counts = array_fill(0, $count, 0);
        for ($i = $count - 1; $i >= 0; $i--) {
            $total = count($children[$i]);
            foreach ($children[$i] as $child) {
                $total += $counts[$child];
            }
            $counts[$i] = $total;
        }

        $this->items = $items;
        $this->parents = $parents;
        $this->children = $children;
        $this->roots = $roots;
        $this->counts = $counts;
    }

    public function isEmpty(): bool
    {
        return $this->items === [];
    }

    /**
     * The siblings of item `$i` (itself included), in document order — the list
     * that carries its `/Prev` and `/Next`.
     *
     * @return list<int>
     */
    public function siblings(int $i): array
    {
        return $this->parents[$i] === -1 ? $this->roots : $this->children[$this->parents[$i]];
    }
}
