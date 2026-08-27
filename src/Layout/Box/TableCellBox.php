<?php

declare(strict_types=1);

namespace Pdf\Layout\Box;

use Pdf\Color\Color;
use Pdf\Geometry\Edges;
use Pdf\Style\VerticalAlign;

/**
 * A single measured table cell.
 */
final readonly class TableCellBox
{
    public function __construct(
        public StackBox $content,
        public int $columnStart,
        public int $columnSpan,
        public float $widthPt,
        public Edges $paddingPt,
        public VerticalAlign $verticalAlign,
        public ?Color $background,
    ) {
    }

    public function contentHeightPt(): float
    {
        return $this->content->contentHeightPt();
    }

    /** Height the cell occupies including its own padding. */
    public function outerHeightPt(): float
    {
        return $this->contentHeightPt() + $this->paddingPt->vertical();
    }

    public function withContent(StackBox $content): self
    {
        return new self(
            $content,
            $this->columnStart,
            $this->columnSpan,
            $this->widthPt,
            $this->paddingPt,
            $this->verticalAlign,
            $this->background,
        );
    }
}
