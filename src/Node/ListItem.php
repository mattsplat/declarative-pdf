<?php

declare(strict_types=1);

namespace Pdf\Node;

use Pdf\Style\StylePatch;
use Pdf\Text\InlineSequence;

/**
 * One item of a {@see BulletList} or {@see OrderedList}.
 *
 * Simple text content can be passed as a string; richer items take a list of
 * block children.
 */
final readonly class ListItem implements BlockNode
{
    /** @var list<BlockNode> */
    public array $children;

    /**
     * @param string|InlineSequence|iterable<BlockNode> $content
     */
    public function __construct(
        string|InlineSequence|iterable $content,
        private StylePatch $patch = new StylePatch(),
    ) {
        if (is_string($content) || $content instanceof InlineSequence) {
            $this->children = [new Paragraph($content, new StylePatch(spaceAfterPt: 0.0))];
        } else {
            $this->children = is_array($content) ? array_values($content) : iterator_to_array($content, false);
        }
    }

    public function patch(): StylePatch
    {
        return $this->patch;
    }
}
