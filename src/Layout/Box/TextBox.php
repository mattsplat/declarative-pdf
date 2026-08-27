<?php

declare(strict_types=1);

namespace Pdf\Layout\Box;

use Pdf\Layout\Box;
use Pdf\Layout\Canvas;
use Pdf\Layout\TextLine;
use Pdf\Style\Style;
use Pdf\Style\TextAlign;

/**
 * A run of laid-out lines from one {@see \Pdf\Node\Heading} or
 * {@see \Pdf\Node\Paragraph}.
 *
 * Splitting happens at line boundaries and honours the style's orphan/widow
 * counts — the pagination behaviour FPDF left entirely to the caller.
 * Rendering ports the alignment maths of `Cell()` (fpdf.php:631-645) and the
 * justification `Tw` of `MultiCell()` (fpdf.php:747).
 */
final class TextBox extends AbstractBox
{
    private const EPSILON = 1e-4;

    /** @param list<TextLine> $lines */
    public function __construct(
        private readonly Style $style,
        private readonly array $lines,
        private readonly bool $keepMarginBefore = true,
        private readonly bool $keepMarginAfter = true,
        private readonly float $minIntrinsicPt = 0.0,
        private readonly float $maxIntrinsicPt = 0.0,
    ) {
    }

    public function minIntrinsicWidthPt(): float
    {
        return $this->minIntrinsicPt;
    }

    public function maxIntrinsicWidthPt(): float
    {
        return $this->maxIntrinsicPt;
    }

    public function contentHeightPt(): float
    {
        $h = 0.0;
        foreach ($this->lines as $line) {
            $h += $line->heightPt;
        }

        return $h;
    }

    public function marginBeforePt(): float
    {
        return $this->keepMarginBefore ? $this->style->spaceBeforePt : 0.0;
    }

    public function marginAfterPt(): float
    {
        return $this->keepMarginAfter ? $this->style->spaceAfterPt : 0.0;
    }

    public function keepWithNext(): bool
    {
        return $this->style->keepWithNext && $this->keepMarginAfter;
    }

    public function keepTogether(): bool
    {
        return $this->style->keepTogether;
    }

    public function split(float $availableHeightPt): array
    {
        $count = count($this->lines);
        if ($count === 0 || $this->contentHeightPt() <= $availableHeightPt + self::EPSILON) {
            return [$this, null];
        }

        $fit = 0;
        $height = 0.0;
        foreach ($this->lines as $line) {
            if ($height + $line->heightPt > $availableHeightPt + self::EPSILON) {
                break;
            }
            $height += $line->heightPt;
            $fit++;
        }

        // Keep at least `widows` lines for the tail.
        if ($count - $fit < $this->style->widows) {
            $fit = $count - $this->style->widows;
        }

        // Require at least `orphans` lines in the head, else move the whole block.
        if ($fit < $this->style->orphans || $fit <= 0) {
            return [null, $this];
        }

        $head = new self(
            $this->style,
            array_slice($this->lines, 0, $fit),
            $this->keepMarginBefore,
            false,
            $this->minIntrinsicPt,
            $this->maxIntrinsicPt,
        );
        $tail = new self(
            $this->style,
            array_slice($this->lines, $fit),
            false,
            $this->keepMarginAfter,
            $this->minIntrinsicPt,
            $this->maxIntrinsicPt,
        );

        return [$head, $tail];
    }

    public function render(Canvas $canvas, float $xPt, float $yTopPt, float $widthPt): void
    {
        $y = $yTopPt;
        foreach ($this->lines as $line) {
            $this->renderLine($canvas, $line, $xPt, $y, $widthPt);
            $y += $line->heightPt;
        }
    }

    private function renderLine(Canvas $canvas, TextLine $line, float $xLeftPt, float $lineTopPt, float $widthPt): void
    {
        if ($line->isEmpty()) {
            return;
        }

        $slack = $widthPt - $line->naturalWidthPt;

        $x = match ($this->style->align) {
            TextAlign::Right => $xLeftPt + max(0.0, $slack),
            TextAlign::Center => $xLeftPt + max(0.0, $slack) / 2,
            default => $xLeftPt,
        };

        $wordSpacing = null;
        if (
            $this->style->align === TextAlign::Justify
            && !$line->isBreakLine
            && $line->justifiableGaps > 0
            && $slack > 0.0
        ) {
            $wordSpacing = $slack / $line->justifiableGaps;
        }

        $baseline = $lineTopPt + $line->ascentPt;
        $cursorX = $x;
        foreach ($line->fragments as $fragment) {
            // Positive baseline shift raises the content (smaller y-from-top).
            $fragmentBaseline = $baseline - $fragment->baselineShiftPt;

            if ($fragment->imageIndex !== null) {
                // Bottom of the image sits on the baseline.
                $imageTop = $fragmentBaseline - $fragment->imageHeightPt;
                $canvas->image($fragment->imageIndex, $cursorX, $imageTop, $fragment->widthPt, $fragment->imageHeightPt);
                if ($fragment->link !== null && $fragment->link !== '') {
                    $canvas->link($cursorX, $imageTop, $fragment->widthPt, $fragment->imageHeightPt, $fragment->link);
                }
                $cursorX += $fragment->widthPt;
                continue;
            }

            if ($fragment->font === null) {
                $cursorX += $fragment->widthPt;
                continue;
            }

            $canvas->text(
                text: $fragment->text,
                xPt: $cursorX,
                baselineYFromTopPt: $fragmentBaseline,
                fontIndex: $fragment->font->index,
                sizePt: $fragment->fontSizePt,
                color: $fragment->color,
                wordSpacingPt: $wordSpacing,
            );

            $advance = $fragment->widthPt;
            if ($wordSpacing !== null) {
                $advance += $wordSpacing * substr_count($fragment->text, ' ');
            }

            $this->drawDecorations($canvas, $fragment, $cursorX, $fragmentBaseline, $advance);

            if ($fragment->link !== null && $fragment->link !== '') {
                $canvas->link($cursorX, $lineTopPt, $advance, $line->heightPt, $fragment->link);
            }

            $cursorX += $advance;
        }
    }

    private function drawDecorations(
        Canvas $canvas,
        \Pdf\Layout\LineFragment $fragment,
        float $xPt,
        float $baselineYPt,
        float $widthPt,
    ): void {
        if ((!$fragment->underline && !$fragment->strikethrough) || $fragment->font === null) {
            return;
        }

        $size = $fragment->fontSizePt;
        $definition = $fragment->font->definition;
        $thickness = max(0.3, $definition->underlineThickness / 1000.0 * $size);

        if ($fragment->underline) {
            // Ports _dounderline() (fpdf.php:1274): position from the font's `up`.
            $top = $baselineYPt - $definition->underlinePosition / 1000.0 * $size;
            $canvas->fillRect($xPt, $top, $widthPt, $thickness, $fragment->color);
        }
        if ($fragment->strikethrough) {
            $canvas->fillRect($xPt, $baselineYPt - 0.28 * $size, $widthPt, $thickness, $fragment->color);
        }
    }

    /** Ascent of the first line, for aligning list markers. */
    public function firstLineAscentPt(): ?float
    {
        return $this->lines[0]->ascentPt ?? null;
    }
}
