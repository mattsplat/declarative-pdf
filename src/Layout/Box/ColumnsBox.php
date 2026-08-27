<?php

declare(strict_types=1);

namespace Pdf\Layout\Box;

use Pdf\Layout\Box;
use Pdf\Layout\Canvas;
use Pdf\Style\Style;

/**
 * N equal-width columns.
 *
 * When the block fits on the page the content is balanced (each column gets
 * roughly total / N height); when it overflows, columns are filled to the page
 * height and the remainder flows to a tail {@see ColumnsBox} on the next page.
 */
final class ColumnsBox extends AbstractBox
{
    private const EPSILON = 1e-4;

    /** @param list<StackBox> $columns */
    public function __construct(
        private readonly array $columns,
        private readonly StackBox $content,
        private readonly float $totalWidthPt,
        private readonly float $columnWidthPt,
        private readonly float $gutterPt,
        private readonly int $count,
        private readonly float $heightPt,
        private readonly Style $style,
        private readonly bool $keepMarginBefore = true,
        private readonly bool $keepMarginAfter = true,
    ) {
    }

    /** Balance $content across $count columns of the given total width. */
    public static function layout(
        StackBox $content,
        int $count,
        float $totalWidthPt,
        float $gutterPt,
        Style $style,
        bool $keepMarginBefore = true,
        bool $keepMarginAfter = true,
    ): self {
        $columnWidth = ($totalWidthPt - $gutterPt * ($count - 1)) / $count;
        $target = $content->contentHeightPt() / $count;

        $columns = self::distribute($content, $count, $target);
        $height = 0.0;
        foreach ($columns as $column) {
            $height = max($height, $column->contentHeightPt());
        }

        return new self(
            $columns,
            $content,
            $totalWidthPt,
            $columnWidth,
            $gutterPt,
            $count,
            $height,
            $style,
            $keepMarginBefore,
            $keepMarginAfter,
        );
    }

    /**
     * @return list<StackBox>
     */
    private static function distribute(StackBox $content, int $count, float $perColumnHeight): array
    {
        $columns = [];
        $remaining = $content;

        for ($c = 1; $c <= $count; $c++) {
            if ($c === $count) {
                $columns[] = $remaining;
                break;
            }
            if ($remaining->isEmpty()) {
                $columns[] = new StackBox([]);
                continue;
            }

            [$head, $tail] = $remaining->split(max(0.0, $perColumnHeight));
            if ($head === null) {
                $first = $remaining->first();
                $head = new StackBox($first !== null ? [$first] : []);
                $tail = $remaining->withoutFirst();
            }
            $columns[] = $head;
            $remaining = $tail ?? new StackBox([]);
        }

        return $columns;
    }

    public function contentHeightPt(): float
    {
        return $this->heightPt;
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

    public function hasForcedBreak(): bool
    {
        return $this->content->hasForcedBreak();
    }

    public function split(float $availableHeightPt): array
    {
        if ($this->heightPt <= $availableHeightPt + self::EPSILON && !$this->content->hasForcedBreak()) {
            return [$this, null];
        }

        // Fill each column to the page height, remainder to the next page.
        $columns = self::distribute($this->content, $this->count, $availableHeightPt);
        $placedHeight = 0.0;
        foreach ($columns as $column) {
            $placedHeight = max($placedHeight, min($column->contentHeightPt(), $availableHeightPt));
        }

        $lastColumn = $columns[$this->count - 1];
        [$lastHead, $lastTail] = $lastColumn->contentHeightPt() > $availableHeightPt + self::EPSILON
            ? $lastColumn->split($availableHeightPt)
            : [$lastColumn, null];

        $columns[$this->count - 1] = $lastHead ?? new StackBox([]);

        /** @var list<Box> $flattened */
        $flattened = [];
        foreach ($columns as $column) {
            $flattened = [...$flattened, ...$column->children()];
        }

        $head = new self(
            $columns,
            new StackBox($flattened),
            $this->totalWidthPt,
            $this->columnWidthPt,
            $this->gutterPt,
            $this->count,
            $placedHeight,
            $this->style,
            $this->keepMarginBefore,
            false,
        );

        if ($lastTail === null || $lastTail->isEmpty()) {
            return [$head, null];
        }

        $tail = self::layout(
            $lastTail,
            $this->count,
            $this->totalWidthPt,
            $this->gutterPt,
            $this->style,
            keepMarginBefore: false,
            keepMarginAfter: $this->keepMarginAfter,
        );

        return [$head, $tail];
    }

    public function minIntrinsicWidthPt(): float
    {
        return $this->content->minIntrinsicWidthPt();
    }

    public function maxIntrinsicWidthPt(): float
    {
        return $this->content->maxIntrinsicWidthPt() * $this->count + $this->gutterPt * ($this->count - 1);
    }

    public function render(Canvas $canvas, float $xPt, float $yTopPt, float $widthPt): void
    {
        $columnX = $xPt;
        foreach ($this->columns as $column) {
            $column->render($canvas, $columnX, $yTopPt, $this->columnWidthPt);
            $columnX += $this->columnWidthPt + $this->gutterPt;
        }
    }
}
