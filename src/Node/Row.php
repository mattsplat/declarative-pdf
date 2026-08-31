<?php

declare(strict_types=1);

namespace Pdf\Node;

use Pdf\Exception\PdfException;
use Pdf\Geometry\Edges;
use Pdf\Style\ColumnWidth;
use Pdf\Style\VerticalAlign;

/**
 * A horizontal stack: children placed side by side in a single borderless
 * {@see Table} row, separated by a fixed gap. Columns size to their content
 * unless a width is given for that slot. Being one row, it never splits.
 */
final readonly class Row extends Component
{
    /** @var list<BlockNode> */
    public array $children;

    /**
     * @param iterable<BlockNode>           $children
     * @param array<int, ColumnWidth|null>  $widths per-child column width, keyed by child index; a missing or null entry sizes to content
     */
    public function __construct(
        iterable $children,
        public float $gapPt = 8.0,
        public VerticalAlign $align = VerticalAlign::Middle,
        public array $widths = [],
    ) {
        $this->children = is_array($children) ? array_values($children) : iterator_to_array($children, false);
        if ($this->children === []) {
            throw new PdfException('A row needs at least one child.');
        }
    }

    public function body(): BlockNode
    {
        $cells = [];
        $columns = [];
        foreach ($this->children as $i => $child) {
            if ($i > 0 && $this->gapPt > 0.0) {
                $cells[] = new TableCell([new Spacer(0.0)]);
                $columns[] = ColumnWidth::fixed($this->gapPt);
            }

            $cells[] = new TableCell([$child], verticalAlign: $this->align);
            $columns[] = $this->widths[$i] ?? ColumnWidth::auto();
        }

        return new Table(
            [new TableRow($cells)],
            $columns,
            borderWidthPt: 0.0,
            cellPaddingPt: new Edges(),
        );
    }
}
