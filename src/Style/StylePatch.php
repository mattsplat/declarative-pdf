<?php

declare(strict_types=1);

namespace Pdf\Style;

use Pdf\Color\Color;
use Pdf\Font\FontFace;
use Pdf\Font\FontStyle;
use Pdf\Geometry\Edges;

/**
 * A sparse set of style overrides. Every null field means "inherit".
 *
 * Three spellings select a font cut, in decreasing precedence: `weight` (the
 * 100–900 scale), `bold` (shorthand for 700 / 400) and the legacy `fontStyle`
 * enum. `italic` is orthogonal to all three.
 */
final readonly class StylePatch
{
    public function __construct(
        public ?string $fontFamily = null,
        public ?FontStyle $fontStyle = null,
        public ?int $weight = null,
        public ?bool $bold = null,
        public ?bool $italic = null,
        public ?float $fontSizePt = null,
        public ?Color $color = null,
        public ?TextAlign $align = null,
        public ?float $lineHeight = null,
        public ?float $spaceBeforePt = null,
        public ?float $spaceAfterPt = null,
        public ?Edges $paddingPt = null,
        public ?Border $border = null,
        public ?Color $background = null,
        public ?bool $underline = null,
        public ?bool $strikethrough = null,
        /** Multiplies the inherited font size (e.g. 0.7 for sub/superscript). */
        public ?float $fontSizeScale = null,
        public ?float $baselineShift = null,
        public ?bool $keepWithNext = null,
        public ?bool $keepTogether = null,
        public ?int $orphans = null,
        public ?int $widows = null,
        /**
         * Space-separated {@see Stylesheet} class-rule names (`'lead callout'`).
         * A selector, not a visual override — {@see self::isEmpty()} ignores it
         * and {@see self::applyTo()} never reads it.
         */
        public ?string $class = null,
    ) {
    }

    public static function none(): self
    {
        return new self();
    }

    /**
     * True when this patch overrides no *visual* property — every field bar
     * `class` (a selector, not a style) is `null`. Drives whether the measurer
     * wraps a component body in an implicit Container.
     */
    public function isEmpty(): bool
    {
        foreach (get_object_vars($this) as $name => $value) {
            if ($name !== 'class' && $value !== null) {
                return false;
            }
        }

        return true;
    }

    /** Superscript: smaller text raised above the baseline. */
    public static function superscript(): self
    {
        return new self(fontSizeScale: 0.7, baselineShift: 0.34);
    }

    /** Subscript: smaller text dropped below the baseline. */
    public static function subscript(): self
    {
        return new self(fontSizeScale: 0.7, baselineShift: -0.16);
    }

    /** Apply this patch on top of a resolved style. */
    public function applyTo(Style $base): Style
    {
        $fontFace = $this->fontStyle?->face() ?? $base->fontFace;
        if ($this->weight !== null || $this->bold !== null || $this->italic !== null) {
            $fontFace = new FontFace(
                $this->weight ?? ($this->bold !== null ? ($this->bold ? 700 : 400) : $fontFace->weight),
                $this->italic ?? $fontFace->italic,
            );
        }

        $fontSizePt = $this->fontSizePt
            ?? ($this->fontSizeScale !== null ? $base->fontSizePt * $this->fontSizeScale : $base->fontSizePt);

        return new Style(
            fontFamily: $this->fontFamily ?? $base->fontFamily,
            fontFace: $fontFace,
            fontSizePt: $fontSizePt,
            color: $this->color ?? $base->color,
            align: $this->align ?? $base->align,
            lineHeight: $this->lineHeight ?? $base->lineHeight,
            spaceBeforePt: $this->spaceBeforePt ?? $base->spaceBeforePt,
            spaceAfterPt: $this->spaceAfterPt ?? $base->spaceAfterPt,
            paddingPt: $this->paddingPt ?? $base->paddingPt,
            border: $this->border ?? $base->border,
            background: $this->background ?? $base->background,
            underline: $this->underline ?? $base->underline,
            strikethrough: $this->strikethrough ?? $base->strikethrough,
            baselineShift: $this->baselineShift ?? $base->baselineShift,
            keepWithNext: $this->keepWithNext ?? $base->keepWithNext,
            keepTogether: $this->keepTogether ?? $base->keepTogether,
            orphans: $this->orphans ?? $base->orphans,
            widows: $this->widows ?? $base->widows,
        );
    }
}
