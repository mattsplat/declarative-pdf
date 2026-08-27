<?php

declare(strict_types=1);

namespace Pdf\Node;

use Pdf\Text\InlineSequence;

final readonly class TableRow
{
    /** @var list<TableCell> */
    public array $cells;

    /**
     * @param iterable<TableCell|string|InlineSequence> $cells
     */
    public function __construct(iterable $cells)
    {
        $normalised = [];
        foreach ($cells as $cell) {
            $normalised[] = $cell instanceof TableCell ? $cell : new TableCell($cell);
        }
        $this->cells = $normalised;
    }

    /** Total number of grid columns this row spans. */
    public function columnCount(): int
    {
        $count = 0;
        foreach ($this->cells as $cell) {
            $count += $cell->colspan;
        }

        return $count;
    }
}
