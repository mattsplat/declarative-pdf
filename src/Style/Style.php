<?php

declare(strict_types=1);

namespace Pdf\Style;

use Pdf\Color\Color;
use Pdf\Font\FontFace;
use Pdf\Geometry\Edges;

/**
 * A fully-resolved set of visual properties for one node.
 *
 * `default()` mirrors FPDF's initial state: Helvetica, regular, 12 pt
 * (fpdf.php:95), black text, left aligned.
 *
 * Inheriting properties: font family/face/size, colour, line height,
 * alignment. Non-inheriting (per-block): spacing, padding, border, background,
 * and the pagination hints. {@see self::resetBlockProperties()} clears the
 * latter group before a child block resolves its own.
 */
final readonly class Style
{
    public function __construct(
        public string $fontFamily,
        public FontFace $fontFace,
        public float $fontSizePt,
        public Color $color,
        public TextAlign $align,
        /** Line box height as a multiple of the font size. */
        public float $lineHeight,
        /** Space above the block, in points. */
        public float $spaceBeforePt,
        /** Space below the block, in points. */
        public float $spaceAfterPt,
        public Edges $paddingPt = new Edges(),
        public Border $border = new Border(),
        public ?Color $background = null,
        public bool $underline = false,
        public bool $strikethrough = false,
        /** Baseline shift as a fraction of the font size; positive raises (superscript). */
        public float $baselineShift = 0.0,
        public bool $keepWithNext = false,
        public bool $keepTogether = false,
        public int $orphans = 2,
        public int $widows = 2,
    ) {
    }

    public static function default(): self
    {
        return new self(
            fontFamily: 'Helvetica',
            fontFace: FontFace::regular(),
            fontSizePt: 12.0,
            color: Color::black(),
            align: TextAlign::Left,
            lineHeight: 1.15,
            spaceBeforePt: 0.0,
            spaceAfterPt: 0.0,
        );
    }

    /** Height of one line box, in points. */
    public function lineHeightPt(): float
    {
        return $this->fontSizePt * $this->lineHeight;
    }

    /** A copy with every non-inheriting property back at its default. */
    public function resetBlockProperties(): self
    {
        return new self(
            fontFamily: $this->fontFamily,
            fontFace: $this->fontFace,
            fontSizePt: $this->fontSizePt,
            color: $this->color,
            align: $this->align,
            lineHeight: $this->lineHeight,
            spaceBeforePt: 0.0,
            spaceAfterPt: 0.0,
            paddingPt: new Edges(),
            border: new Border(),
            background: null,
            underline: $this->underline,
            strikethrough: $this->strikethrough,
            baselineShift: $this->baselineShift,
            keepWithNext: false,
            keepTogether: false,
            orphans: 2,
            widows: 2,
        );
    }
}
