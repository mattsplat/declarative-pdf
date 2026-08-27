<?php

declare(strict_types=1);

namespace Pdf\Node;

use Pdf\Style\StylePatch;

final readonly class BulletList implements BlockNode
{
    /** @var list<ListItem> */
    public array $items;

    /**
     * @param iterable<ListItem|string> $items
     */
    public function __construct(
        iterable $items = [],
        public string $marker = "\u{2022}", // bullet
        public float $gutterPt = 18.0,
        public float $itemSpacingPt = 3.0,
        private StylePatch $patch = new StylePatch(),
    ) {
        $normalised = [];
        foreach ($items as $item) {
            $normalised[] = $item instanceof ListItem ? $item : new ListItem($item);
        }
        $this->items = $normalised;
    }

    public function patch(): StylePatch
    {
        return $this->patch;
    }
}
