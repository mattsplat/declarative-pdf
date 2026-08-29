<?php

declare(strict_types=1);

namespace Pdf\Node;

/**
 * An entry in the document outline (the viewer's bookmarks panel). It points at
 * an existing {@see Anchor} by name; the anchor's resolved page and Y become
 * the bookmark's destination after layout.
 *
 * Nesting is by `level` in document order: a `level` 1 item is a child of the
 * nearest preceding `level` 0 item, and so on. A jump deeper than one level
 * past the current parent is clamped to parent + 1.
 */
final readonly class Bookmark
{
    public function __construct(
        public string $title,
        public string $anchor,
        public int $level = 0,
    ) {
    }
}
