<?php

declare(strict_types=1);

namespace Pdf\Layout\Box;

/**
 * A measured table row: its cells and its resolved height (the tallest cell).
 */
final readonly class TableRowBox
{
    /** @param list<TableCellBox> $cells */
    public function __construct(
        public array $cells,
        public float $heightPt,
        public bool $isHeader,
    ) {
    }

    /** @param list<TableCellBox> $cells */
    public static function fromCells(array $cells, bool $isHeader, float $minHeightPt = 0.0): self
    {
        $height = $minHeightPt;
        foreach ($cells as $cell) {
            $height = max($height, $cell->outerHeightPt());
        }

        return new self($cells, $height, $isHeader);
    }
}
