<?php

declare(strict_types=1);

namespace Pdf\Geometry;

/** A position in user space (origin top-left, y increasing downward). */
final readonly class Point
{
    public function __construct(
        public float $x,
        public float $y,
    ) {
    }

    public function translate(float $dx, float $dy): self
    {
        return new self($this->x + $dx, $this->y + $dy);
    }
}
