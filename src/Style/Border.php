<?php

declare(strict_types=1);

namespace Pdf\Style;

use Pdf\Color\Color;
use Pdf\Geometry\Edges;

/**
 * A box border: a per-edge width (points), a single colour, and an optional
 * uniform corner radius.
 */
final readonly class Border
{
    public function __construct(
        public Edges $widthPt = new Edges(),
        public Color $color = new Color(0, 0, 0),
        public float $radiusPt = 0.0,
    ) {
    }

    public static function none(): self
    {
        return new self();
    }

    public static function uniform(float $widthPt, Color $color = new Color(0, 0, 0), float $radiusPt = 0.0): self
    {
        return new self(Edges::all($widthPt), $color, $radiusPt);
    }

    public function isVisible(): bool
    {
        return !$this->widthPt->isZero();
    }

    public function isRounded(): bool
    {
        return $this->radiusPt > 0.0;
    }

    public function withoutTop(): self
    {
        $w = $this->widthPt;

        return new self(new Edges(0.0, $w->right, $w->bottom, $w->left), $this->color, $this->radiusPt);
    }

    public function withoutBottom(): self
    {
        $w = $this->widthPt;

        return new self(new Edges($w->top, $w->right, 0.0, $w->left), $this->color, $this->radiusPt);
    }

    public function withRadius(float $radiusPt): self
    {
        return new self($this->widthPt, $this->color, $radiusPt);
    }

    /**
     * Per-corner radii for a fragment whose top and/or bottom edge was
     * suppressed by pagination — the corners on a suppressed edge stay square
     * since the box continues past the page break there.
     *
     * @return array{0: float, 1: float, 2: float, 3: float} top-left, top-right, bottom-right, bottom-left
     */
    public function cornerRadiiPt(bool $suppressTop = false, bool $suppressBottom = false): array
    {
        $top = $suppressTop ? 0.0 : $this->radiusPt;
        $bottom = $suppressBottom ? 0.0 : $this->radiusPt;

        return [$top, $top, $bottom, $bottom];
    }
}
