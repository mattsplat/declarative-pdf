<?php

declare(strict_types=1);

namespace Pdf\Style;

use Pdf\Color\Color;
use Pdf\Geometry\Edges;

/**
 * A box border: a per-edge width (points) and a single colour.
 */
final readonly class Border
{
    public function __construct(
        public Edges $widthPt = new Edges(),
        public Color $color = new Color(0, 0, 0),
    ) {
    }

    public static function none(): self
    {
        return new self();
    }

    public static function uniform(float $widthPt, Color $color = new Color(0, 0, 0)): self
    {
        return new self(Edges::all($widthPt), $color);
    }

    public function isVisible(): bool
    {
        return !$this->widthPt->isZero();
    }

    public function withoutTop(): self
    {
        $w = $this->widthPt;

        return new self(new Edges(0.0, $w->right, $w->bottom, $w->left), $this->color);
    }

    public function withoutBottom(): self
    {
        $w = $this->widthPt;

        return new self(new Edges($w->top, $w->right, 0.0, $w->left), $this->color);
    }
}
