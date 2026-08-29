<?php

declare(strict_types=1);

namespace Pdf\Style;

use Pdf\Color\Color;

/**
 * How a {@see \Pdf\Node\Path} is painted: solid fill, solid stroke, or both.
 *
 * Gradients (`/Shading`) and dash arrays are deliberately absent — see the
 * "Vector drawing" section of `docs/roadmap.md`.
 */
final readonly class Paint
{
    public ?Color $stroke;

    public function __construct(
        public ?Color $fill = null,
        ?Color $stroke = null,
        public float $strokeWidthPt = 0.5,
        public FillRule $fillRule = FillRule::NonZero,
        public LineCap $lineCap = LineCap::Butt,
        public LineJoin $lineJoin = LineJoin::Miter,
    ) {
        // A paint with neither half would draw nothing, which is never what the
        // caller meant; an unpainted path defaults to a hairline black outline.
        $this->stroke = $stroke ?? ($fill === null ? Color::black() : null);
    }

    public static function filled(Color $color, FillRule $fillRule = FillRule::NonZero): self
    {
        return new self(fill: $color, fillRule: $fillRule);
    }

    public static function stroked(
        Color $color,
        float $widthPt = 0.5,
        LineCap $lineCap = LineCap::Butt,
        LineJoin $lineJoin = LineJoin::Miter,
    ): self {
        return new self(stroke: $color, strokeWidthPt: $widthPt, lineCap: $lineCap, lineJoin: $lineJoin);
    }

    public function fills(): bool
    {
        return $this->fill !== null;
    }

    public function strokes(): bool
    {
        return $this->stroke !== null && $this->strokeWidthPt > 0.0;
    }

    /**
     * The path-painting operator this paint ends with, or null when it would
     * mark nothing at all.
     */
    public function operator(): ?string
    {
        $suffix = $this->fillRule->operatorSuffix();

        return match (true) {
            $this->fills() && $this->strokes() => 'B' . $suffix,
            $this->fills() => 'f' . $suffix,
            $this->strokes() => 'S',
            default => null,
        };
    }
}
