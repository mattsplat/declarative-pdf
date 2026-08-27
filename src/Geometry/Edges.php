<?php

declare(strict_types=1);

namespace Pdf\Geometry;

/**
 * Four edge insets in user units. Used for margins, padding and border widths.
 */
final readonly class Edges
{
    public function __construct(
        public float $top = 0.0,
        public float $right = 0.0,
        public float $bottom = 0.0,
        public float $left = 0.0,
    ) {
    }

    public static function zero(): self
    {
        return new self();
    }

    public static function all(float $value): self
    {
        return new self($value, $value, $value, $value);
    }

    public static function symmetric(float $vertical, float $horizontal): self
    {
        return new self($vertical, $horizontal, $vertical, $horizontal);
    }

    public function horizontal(): float
    {
        return $this->left + $this->right;
    }

    public function vertical(): float
    {
        return $this->top + $this->bottom;
    }

    public function isZero(): bool
    {
        return $this->top === 0.0 && $this->right === 0.0
            && $this->bottom === 0.0 && $this->left === 0.0;
    }
}
