<?php

declare(strict_types=1);

namespace Pdf\Svg;

use Pdf\Color\Color;
use Pdf\Exception\SvgException;
use Pdf\Style\FillRule;
use Pdf\Style\LineCap;
use Pdf\Style\LineJoin;

/**
 * The SVG presentation properties in effect for one element, after inheritance.
 *
 * SVG resolves paint through a cascade; without stylesheet support (see
 * {@see SvgParser} on `<style>`) that reduces to inheriting the parent's values
 * and letting an element's own presentation attributes and inline `style`
 * attribute override them, which is what {@see self::with()} does.
 *
 * Lengths here are still in SVG user units — {@see SvgParser} converts to
 * points once, when it emits geometry.
 */
final readonly class SvgStyle
{
    /**
     * Properties that would visibly change the drawing but have no equivalent
     * in {@see \Pdf\Style\Paint}. Silently dropping one renders a lie, so they
     * are refused.
     */
    private const REFUSED = [
        'filter' => 'filters',
        'mask' => 'masks',
        'clip-path' => 'clipping paths',
        'stroke-dasharray' => 'dashed strokes',
        'marker-start' => 'markers',
        'marker-mid' => 'markers',
        'marker-end' => 'markers',
    ];

    public function __construct(
        public ?Color $fill = new Color(0, 0, 0),
        public ?Color $stroke = null,
        public ?string $fillGradient = null,
        public float $strokeWidth = 1.0,
        public LineCap $lineCap = LineCap::Butt,
        public LineJoin $lineJoin = LineJoin::Miter,
        public FillRule $fillRule = FillRule::NonZero,
        public float $fillOpacity = 1.0,
        public float $strokeOpacity = 1.0,
        /** Alpha carried by the fill colour itself (`#rrggbbaa`, `rgba()`). */
        public float $fillColorAlpha = 1.0,
        public float $strokeColorAlpha = 1.0,
        /**
         * `opacity` on an ancestor. It composites a whole subtree rather than
         * being inherited, so it accumulates down the tree instead of being
         * overridden by a child's own `fill-opacity`.
         */
        public float $groupOpacity = 1.0,
        public Color $currentColor = new Color(0, 0, 0),
        public bool $hidden = false,
    ) {
    }

    /**
     * The three sources of transparency multiplied together, as CSS composes
     * them: the paint's own alpha, its `*-opacity`, and every enclosing
     * `opacity`.
     */
    public function effectiveFillAlpha(): float
    {
        return $this->fillOpacity * $this->fillColorAlpha * $this->groupOpacity;
    }

    public function effectiveStrokeAlpha(): float
    {
        return $this->strokeOpacity * $this->strokeColorAlpha * $this->groupOpacity;
    }

    /**
     * This style overlaid with an element's own properties.
     *
     * @param array<string, string> $properties CSS property name => value
     */
    public function with(array $properties): self
    {
        $fill = $this->fill;
        $fillGradient = $this->fillGradient;
        $stroke = $this->stroke;
        $strokeWidth = $this->strokeWidth;
        $lineCap = $this->lineCap;
        $lineJoin = $this->lineJoin;
        $fillRule = $this->fillRule;
        $fillOpacity = $this->fillOpacity;
        $strokeOpacity = $this->strokeOpacity;
        $fillColorAlpha = $this->fillColorAlpha;
        $strokeColorAlpha = $this->strokeColorAlpha;
        $groupOpacity = $this->groupOpacity;
        $currentColor = $this->currentColor;
        $hidden = $this->hidden
            || in_array(strtolower(trim($properties['display'] ?? '')), ['none'], true)
            || in_array(strtolower(trim($properties['visibility'] ?? '')), ['hidden', 'collapse'], true);

        // `color` first: it is what currentColor resolves to for its siblings.
        if (isset($properties['color'])) {
            $currentColor = SvgColor::parse($properties['color'], $currentColor) ?? $currentColor;
        }

        foreach ($properties as $name => $value) {
            $value = trim($value);
            if ($value === '' || strtolower($value) === 'inherit') {
                continue;
            }

            // Nothing this element declares will be drawn, so an effect we
            // cannot reproduce cannot mislead anyone either.
            if (!$hidden && isset(self::REFUSED[$name]) && strtolower($value) !== 'none') {
                throw new SvgException(sprintf('Unsupported SVG feature: %s (%s).', self::REFUSED[$name], $name));
            }

            switch ($name) {
                case 'fill':
                    $reference = self::gradientReference($value);
                    if ($reference !== null) {
                        $fillGradient = $reference;
                        $fill = null;
                        break;
                    }
                    $fillGradient = null;
                    $fill = SvgColor::parse($value, $currentColor);
                    // Replaces, never accumulates: a later `fill` declaration
                    // supersedes an earlier one's alpha along with its colour.
                    $fillColorAlpha = SvgColor::alpha($value);
                    break;

                case 'stroke':
                    if (self::gradientReference($value) !== null) {
                        // Paint carries a Gradient for the fill only; a PDF
                        // stroke takes a plain colour operand.
                        throw new SvgException('Unsupported SVG feature: a gradient used as a stroke.');
                    }
                    $stroke = SvgColor::parse($value, $currentColor);
                    $strokeColorAlpha = SvgColor::alpha($value);
                    break;

                case 'stroke-width':
                    $strokeWidth = max(0.0, SvgLength::userUnits($value));
                    break;

                case 'stroke-linecap':
                    $lineCap = match (strtolower($value)) {
                        'butt' => LineCap::Butt,
                        'round' => LineCap::Round,
                        'square' => LineCap::Square,
                        default => throw new SvgException('Unknown stroke-linecap: ' . $value),
                    };
                    break;

                case 'stroke-linejoin':
                    $lineJoin = match (strtolower($value)) {
                        'miter', 'miter-clip', 'arcs' => LineJoin::Miter,
                        'round' => LineJoin::Round,
                        'bevel' => LineJoin::Bevel,
                        default => throw new SvgException('Unknown stroke-linejoin: ' . $value),
                    };
                    break;

                case 'fill-rule':
                    $fillRule = match (strtolower($value)) {
                        'nonzero' => FillRule::NonZero,
                        'evenodd' => FillRule::EvenOdd,
                        default => throw new SvgException('Unknown fill-rule: ' . $value),
                    };
                    break;

                case 'fill-opacity':
                    $fillOpacity = SvgColor::opacity($value);
                    break;

                case 'stroke-opacity':
                    $strokeOpacity = SvgColor::opacity($value);
                    break;

                case 'opacity':
                    // Applying group opacity per shape is the usual
                    // approximation; it differs from a true group composite
                    // only where shapes inside the group overlap each other.
                    $groupOpacity *= SvgColor::opacity($value);
                    break;
            }
        }

        return new self(
            $fill,
            $stroke,
            $fillGradient,
            $strokeWidth,
            $lineCap,
            $lineJoin,
            $fillRule,
            $fillOpacity,
            $strokeOpacity,
            $fillColorAlpha,
            $strokeColorAlpha,
            $groupOpacity,
            $currentColor,
            $hidden,
        );
    }

    /** True when this style would put no ink on the page at all. */
    public function paintsNothing(): bool
    {
        $noFill = $this->fill === null && $this->fillGradient === null;
        $noStroke = $this->stroke === null || $this->strokeWidth <= 0.0;

        return $this->hidden || ($noFill && $noStroke);
    }

    /** The `#id` inside a `url(#id)` paint reference, or null. */
    private static function gradientReference(string $value): ?string
    {
        if (preg_match('/^url\(\s*[\'"]?#([^\'")\s]+)[\'"]?\s*\)/i', $value, $match) === 1) {
            return $match[1];
        }

        return null;
    }
}
