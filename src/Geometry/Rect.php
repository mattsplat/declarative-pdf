<?php

declare(strict_types=1);

namespace Pdf\Geometry;

/** An axis-aligned rectangle in user space (top-left origin). */
final readonly class Rect
{
    public function __construct(
        public float $x,
        public float $y,
        public float $width,
        public float $height,
    ) {
    }

    public function right(): float
    {
        return $this->x + $this->width;
    }

    public function bottom(): float
    {
        return $this->y + $this->height;
    }

    public function origin(): Point
    {
        return new Point($this->x, $this->y);
    }

    public function size(): Size
    {
        return new Size($this->width, $this->height);
    }

    /** Shrink the rectangle inward by the given edge insets. */
    public function deflate(Edges $insets): self
    {
        return new self(
            $this->x + $insets->left,
            $this->y + $insets->top,
            $this->width - $insets->horizontal(),
            $this->height - $insets->vertical(),
        );
    }

    public function translate(float $dx, float $dy): self
    {
        return new self($this->x + $dx, $this->y + $dy, $this->width, $this->height);
    }
}
