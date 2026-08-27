<?php

declare(strict_types=1);

namespace Pdf\Node;

use Pdf\Exception\PdfException;
use Pdf\Style\StylePatch;

/**
 * Flows its children through N equal-width columns, balancing them when the
 * block fits on the page and filling column-by-column (then page-by-page) when
 * it does not.
 *
 * Replaces the multi-column trick of overriding `AcceptPageBreak()` (tuto4 /
 * fpdf.php:574) with a first-class block.
 */
final readonly class Columns implements BlockNode
{
    /** @var list<BlockNode> */
    public array $children;

    /** @param iterable<BlockNode> $children */
    public function __construct(
        iterable $children,
        public int $count = 2,
        public float $gutterPt = 14.0,
        private StylePatch $patch = new StylePatch(),
    ) {
        if ($count < 2) {
            throw new PdfException('Columns needs a count of at least 2.');
        }
        $this->children = is_array($children) ? array_values($children) : iterator_to_array($children, false);
    }

    public function patch(): StylePatch
    {
        return $this->patch;
    }
}
