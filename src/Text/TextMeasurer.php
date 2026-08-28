<?php

declare(strict_types=1);

namespace Pdf\Text;

use Pdf\Font\FontRegistry;
use Pdf\Font\FontRepository;
use Pdf\Font\FontStyle;
use Pdf\Style\Style;

/**
 * Measures the advance width of a single unbroken run of text without going
 * through the layout engine.
 *
 * The text is transcoded to the resolved font's encoding first, exactly as
 * {@see \Pdf\Layout\Measurer::resolveRuns()} does, so a width from here agrees
 * with what the line breaker computes for the same string.
 */
final readonly class TextMeasurer
{
    public function __construct(private FontRegistry $fonts)
    {
    }

    public static function withBundledFonts(): self
    {
        return new self(new FontRegistry(FontRepository::withBundledFonts()));
    }

    /** Width in points of `$text` set on one line (no wrapping, no `\n`) in `$style`. */
    public function width(string $text, Style $style): float
    {
        return $this->widthOf($text, $style->fontFamily, $style->fontStyle, $style->fontSizePt);
    }

    /** Width in points of `$text` set from an explicit family / style / size. */
    public function widthOf(string $text, string $fontFamily, FontStyle $fontStyle, float $fontSizePt): float
    {
        $font = $this->fonts->use($fontFamily, $fontStyle);
        $encoded = Encoding::forFont($text, $font->definition->encoding);

        return $font->metrics->stringWidth($encoded, $fontSizePt);
    }
}
