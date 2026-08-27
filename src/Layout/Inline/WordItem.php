<?php

declare(strict_types=1);

namespace Pdf\Layout\Inline;

use Pdf\Layout\RunStyle;

/**
 * A maximal run of non-space, non-newline text in a single style.
 */
final readonly class WordItem implements InlineItem
{
    public function __construct(
        public string $text,
        public RunStyle $style,
        public float $widthPt,
    ) {
    }

    public static function of(string $text, RunStyle $style): self
    {
        return new self($text, $style, $style->widthOf($text));
    }

    public function widthPt(): float
    {
        return $this->widthPt;
    }

    /**
     * Break the word at the widest prefix that fits (at least one character) —
     * the last-resort mid-word break of `MultiCell()` (fpdf.php:732-742).
     *
     * @return array{0: self, 1: ?self}
     */
    public function splitAt(float $maxWidthPt): array
    {
        $metrics = $this->style->font->metrics;
        $size = $this->style->fontSizePt;
        $length = strlen($this->text);

        $accumulated = 0.0;
        $fit = 0;
        for ($i = 0; $i < $length; $i++) {
            $advance = $metrics->charAdvance($this->text[$i]) * $size / 1000.0;
            if ($fit > 0 && $accumulated + $advance > $maxWidthPt) {
                break;
            }
            $accumulated += $advance;
            $fit = $i + 1;
        }

        $fit = max(1, $fit);
        if ($fit >= $length) {
            return [$this, null];
        }

        return [
            self::of(substr($this->text, 0, $fit), $this->style),
            self::of(substr($this->text, $fit), $this->style),
        ];
    }
}
