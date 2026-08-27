<?php

declare(strict_types=1);

namespace Pdf\Geometry;

final readonly class Size
{
    public function __construct(
        public float $width,
        public float $height,
    ) {
    }

    public function withHeight(float $height): self
    {
        return new self($this->width, $height);
    }

    public function withWidth(float $width): self
    {
        return new self($width, $this->height);
    }
}
