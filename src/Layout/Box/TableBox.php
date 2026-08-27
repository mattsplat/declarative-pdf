<?php

declare(strict_types=1);

namespace Pdf\Layout\Box;

use Pdf\Color\Color;
use Pdf\Geometry\Edges;
use Pdf\Layout\Canvas;
use Pdf\Style\Style;
use Pdf\Style\VerticalAlign;

/**
 * A laid-out table.
 *
 * Column widths are already resolved (see {@see \Pdf\Layout\TableLayout}). The
 * box splits at row boundaries; an oversized single row is split by dividing
 * each cell's content; header rows repeat at the top of every page fragment
 * when `repeatHeader` is set. Borders are drawn per cell, which keeps colspan
 * correct (mirrors tuto5's per-cell `LRTB`, fpdf.php:34-53).
 */
final class TableBox extends AbstractBox
{
    private const EPSILON = 1e-4;

    /**
     * @param list<float>        $columnWidths
     * @param list<TableRowBox>  $rows          includes the header rows at the front
     * @param list<TableRowBox>  $headerRows    the leading rows to repeat on each fragment
     */
    public function __construct(
        private readonly array $columnWidths,
        private readonly array $rows,
        private readonly array $headerRows,
        private readonly float $borderWidthPt,
        private readonly Color $borderColor,
        private readonly ?Color $headerBackground,
        private readonly Style $style,
        private readonly bool $repeatHeader,
        private readonly bool $keepMarginBefore = true,
        private readonly bool $keepMarginAfter = true,
    ) {
    }

    private function totalWidthPt(): float
    {
        return array_sum($this->columnWidths);
    }

    public function contentHeightPt(): float
    {
        $h = 0.0;
        foreach ($this->rows as $row) {
            $h += $row->heightPt;
        }

        return $h;
    }

    public function marginBeforePt(): float
    {
        return $this->keepMarginBefore ? $this->style->spaceBeforePt : 0.0;
    }

    public function marginAfterPt(): float
    {
        return $this->keepMarginAfter ? $this->style->spaceAfterPt : 0.0;
    }

    public function keepWithNext(): bool
    {
        return $this->style->keepWithNext && $this->keepMarginAfter;
    }

    public function keepTogether(): bool
    {
        return $this->style->keepTogether;
    }

    public function minIntrinsicWidthPt(): float
    {
        return $this->totalWidthPt();
    }

    public function maxIntrinsicWidthPt(): float
    {
        return $this->totalWidthPt();
    }

    public function split(float $availableHeightPt): array
    {
        if ($this->contentHeightPt() <= $availableHeightPt + self::EPSILON || $this->style->keepTogether) {
            return $this->contentHeightPt() <= $availableHeightPt + self::EPSILON ? [$this, null] : [null, $this];
        }

        $headerCount = count($this->headerRows);
        $headerHeight = 0.0;
        foreach ($this->headerRows as $row) {
            $headerHeight += $row->heightPt;
        }
        if ($headerHeight > $availableHeightPt + self::EPSILON) {
            return [null, $this];
        }

        /** @var list<TableRowBox> $head */
        $head = array_slice($this->rows, 0, $headerCount);
        $y = $headerHeight;
        $i = $headerCount;
        $n = count($this->rows);
        /** @var list<TableRowBox> $tailExtra */
        $tailExtra = [];

        for (; $i < $n; $i++) {
            $row = $this->rows[$i];

            if ($y + $row->heightPt <= $availableHeightPt + self::EPSILON) {
                $head[] = $row;
                $y += $row->heightPt;
                continue;
            }

            $placedBodyRows = count($head) - $headerCount;
            if ($placedBodyRows === 0) {
                [$rowHead, $rowTail] = $this->splitRow($row, $availableHeightPt - $y);
                if ($rowHead === null) {
                    // Cannot divide the row; let it overflow this page.
                    $head[] = $row;
                    $i++;
                } else {
                    $head[] = $rowHead;
                    $i++;
                    if ($rowTail !== null) {
                        $tailExtra = [$rowTail];
                    }
                }
            }
            break;
        }

        $remaining = [...$tailExtra, ...array_slice($this->rows, $i)];
        if ($remaining === []) {
            return [$this->fragment($head, $this->headerRows, keepBefore: $this->keepMarginBefore, keepAfter: $this->keepMarginAfter), null];
        }

        // The tail only carries header rows (and a header count for its own
        // later splits) when they are actually repeated.
        [$tailRows, $tailHeaders] = $this->repeatHeader
            ? [[...$this->headerRows, ...$remaining], $this->headerRows]
            : [$remaining, []];

        return [
            $this->fragment($head, $this->headerRows, keepBefore: $this->keepMarginBefore, keepAfter: false),
            $this->fragment($tailRows, $tailHeaders, keepBefore: false, keepAfter: $this->keepMarginAfter),
        ];
    }

    /**
     * @return array{0: ?TableRowBox, 1: ?TableRowBox}
     */
    private function splitRow(TableRowBox $row, float $availableHeightPt): array
    {
        /** @var list<TableCellBox> $headCells */
        $headCells = [];
        /** @var list<TableCellBox> $tailCells */
        $tailCells = [];
        $anyTail = false;

        foreach ($row->cells as $cell) {
            $cellAvailable = $availableHeightPt - $cell->paddingPt->vertical();
            [$contentHead, $contentTail] = $cell->content->split(max(0.0, $cellAvailable));

            if ($contentHead === null) {
                return [null, $row];
            }

            $headCells[] = $cell->withContent($contentHead);
            if ($contentTail !== null) {
                $anyTail = true;
                $tailCells[] = $cell->withContent($contentTail);
            } else {
                $tailCells[] = $cell->withContent(new StackBox([]));
            }
        }

        if (!$anyTail) {
            return [$row, null];
        }

        return [
            TableRowBox::fromCells($headCells, $row->isHeader),
            TableRowBox::fromCells($tailCells, $row->isHeader),
        ];
    }

    /**
     * @param list<TableRowBox> $rows
     * @param list<TableRowBox> $headerRows the leading rows to treat as headers
     */
    private function fragment(array $rows, array $headerRows, bool $keepBefore, bool $keepAfter): self
    {
        return new self(
            $this->columnWidths,
            $rows,
            $headerRows,
            $this->borderWidthPt,
            $this->borderColor,
            $this->headerBackground,
            $this->style,
            $this->repeatHeader,
            $keepBefore,
            $keepAfter,
        );
    }

    public function render(Canvas $canvas, float $xPt, float $yTopPt, float $widthPt): void
    {
        $columnX = [$xPt];
        $acc = $xPt;
        foreach ($this->columnWidths as $w) {
            $acc += $w;
            $columnX[] = $acc;
        }

        $y = $yTopPt;
        foreach ($this->rows as $row) {
            foreach ($row->cells as $cell) {
                $cellX = $columnX[$cell->columnStart];
                $cellW = $cell->widthPt;

                $background = $cell->background
                    ?? ($row->isHeader ? $this->headerBackground : null);
                if ($background !== null) {
                    $canvas->fillRect($cellX, $y, $cellW, $row->heightPt, $background);
                }

                if ($this->borderWidthPt > 0.0) {
                    $canvas->strokeEdges(
                        $cellX,
                        $y,
                        $cellW,
                        $row->heightPt,
                        Edges::all($this->borderWidthPt),
                        $this->borderColor,
                    );
                }

                $freeSpace = $row->heightPt - $cell->paddingPt->vertical() - $cell->contentHeightPt();
                $offset = match ($cell->verticalAlign) {
                    VerticalAlign::Middle => max(0.0, $freeSpace / 2),
                    VerticalAlign::Bottom => max(0.0, $freeSpace),
                    VerticalAlign::Top => 0.0,
                };

                $cell->content->render(
                    $canvas,
                    $cellX + $cell->paddingPt->left,
                    $y + $cell->paddingPt->top + $offset,
                    $cellW - $cell->paddingPt->horizontal(),
                );
            }
            $y += $row->heightPt;
        }
    }
}
