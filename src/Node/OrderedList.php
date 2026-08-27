<?php

declare(strict_types=1);

namespace Pdf\Node;

use Pdf\Style\StylePatch;

final readonly class OrderedList implements BlockNode
{
    /** @var list<ListItem> */
    public array $items;

    /**
     * @param iterable<ListItem|string> $items
     */
    public function __construct(
        iterable $items = [],
        public int $start = 1,
        public string $suffix = '.',
        public float $gutterPt = 22.0,
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
