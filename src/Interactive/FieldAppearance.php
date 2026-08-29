<?php

declare(strict_types=1);

namespace Pdf\Interactive;

use Pdf\Color\Color;
use Pdf\Font\ResolvedFont;

/**
 * The resolved look of one field: the font and colours its self-drawn
 * appearance stream is painted with, plus the `/DA` default-appearance string
 * the viewer would use if it ever regenerated the appearance itself.
 *
 * `fontSizePt` of `0.0` means auto-size (the PDF convention): the appearance
 * builder then picks a size from the widget height.
 */
final readonly class FieldAppearance
{
    public function __construct(
        public ResolvedFont $font,
        public float $fontSizePt,
        public Color $textColor,
        public ?Color $borderColor,
        public ?Color $backgroundColor,
        public float $borderWidthPt,
        /** 0 = left, 1 = centred, 2 = right (`/Q`). */
        public int $quadding,
    ) {
    }

    public function fontEncoding(): ?string
    {
        return $this->font->definition->encoding;
    }

    /** The `/DA` string, e.g. `/F1 0 Tf 0 g`. */
    public function defaultAppearance(): string
    {
        return sprintf('/F%d %s Tf %s', $this->font->index, self::num($this->fontSizePt), $this->textColor->fillOp());
    }

    /** Trim a float the way PDF content streams do — no trailing zeros. */
    public static function num(float $value): string
    {
        $s = rtrim(rtrim(sprintf('%.2F', $value), '0'), '.');

        return $s === '' || $s === '-0' ? '0' : $s;
    }
}
