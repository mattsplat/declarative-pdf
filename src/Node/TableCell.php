<?php

declare(strict_types=1);

namespace Pdf\Node;

use Pdf\Exception\PdfException;
use Pdf\Style\StylePatch;
use Pdf\Style\VerticalAlign;
use Pdf\Text\InlineSequence;

/**
 * One table cell. Simple text can be passed as a string; richer cells take a
 * list of block children.
 */
final readonly class TableCell
{
    /** @var list<BlockNode> */
    public array $children;

    /**
     * @param string|InlineSequence|iterable<BlockNode> $content
     */
    public function __construct(
        string|InlineSequence|iterable $content,
        public int $colspan = 1,
        public VerticalAlign $verticalAlign = VerticalAlign::Top,
        public StylePatch $patch = new StylePatch(),
        public ?\Pdf\Color\Color $background = null,
    ) {
        if ($colspan < 1) {
            throw new PdfException('Cell colspan must be at least 1.');
        }

        if (is_string($content) || $content instanceof InlineSequence) {
            $this->children = [new Paragraph($content, new StylePatch(spaceAfterPt: 0.0))];
        } else {
            $this->children = is_array($content) ? array_values($content) : iterator_to_array($content, false);
        }
    }
}
