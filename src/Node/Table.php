<?php

declare(strict_types=1);

namespace Pdf\Node;

use Pdf\Color\Color;
use Pdf\Exception\PdfException;
use Pdf\Geometry\Edges;
use Pdf\Style\ColumnWidth;
use Pdf\Style\StylePatch;

/**
 * A table with automatic column sizing.
 *
 * Replaces the hand-built `Cell()` grids of tuto5 (fpdf.php:34-53): column
 * widths are computed from content, rows split across pages, and the header
 * rows can repeat on every page.
 */
final readonly class Table implements BlockNode
{
    /** @var list<ColumnWidth> */
    public array $columns;

    /** @var list<TableRow> */
    public array $rows;

    /**
     * @param iterable<TableRow>     $rows
     * @param list<ColumnWidth>|null $columns    one per grid column; defaults to all-auto
     */
    public function __construct(
        iterable $rows,
        ?array $columns = null,
        public ?float $totalWidthPt = null,
        public int $headerRows = 0,
        public bool $repeatHeader = true,
        public float $borderWidthPt = 0.5,
        public Color $borderColor = new Color(0, 0, 0),
        public Edges $cellPaddingPt = new Edges(3.0, 4.0, 3.0, 4.0),
        public ?Color $headerBackground = null,
        private StylePatch $patch = new StylePatch(),
    ) {
        $this->rows = is_array($rows) ? array_values($rows) : iterator_to_array($rows, false);
        if ($this->rows === []) {
            throw new PdfException('A table needs at least one row.');
        }

        $gridColumns = 0;
        foreach ($this->rows as $row) {
            $gridColumns = max($gridColumns, $row->columnCount());
        }

        if ($columns === null) {
            $this->columns = array_fill(0, $gridColumns, ColumnWidth::auto());
        } elseif (count($columns) !== $gridColumns) {
            throw new PdfException(sprintf(
                'Table has %d grid columns but %d column specs were given.',
                $gridColumns,
                count($columns),
            ));
        } else {
            $this->columns = $columns;
        }
    }

    public function patch(): StylePatch
    {
        return $this->patch;
    }
}
