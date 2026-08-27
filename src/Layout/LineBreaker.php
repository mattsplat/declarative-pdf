<?php

declare(strict_types=1);

namespace Pdf\Layout;

use Pdf\Color\Color;
use Pdf\Layout\Inline\BoxItem;
use Pdf\Layout\Inline\BreakItem;
use Pdf\Layout\Inline\InlineItem;
use Pdf\Layout\Inline\SpaceItem;
use Pdf\Layout\Inline\WordItem;

/**
 * Greedy line breaking over an item stream (words, spaces, hard breaks, inline
 * boxes).
 *
 * Same greedy strategy as `MultiCell()` (fpdf.php:661-763) — fill a line, break
 * at the last space, force a mid-word break when a single word overflows — but
 * it works across runs of differing font/size and lets non-text atoms (inline
 * images) take part in wrapping.
 *
 * Text is treated as single-byte encoded, matching FPDF's core-font handling.
 */
final class LineBreaker
{
    private const EPSILON = 1e-4;
    private const MAX_ITERATIONS = 1_000_000;

    /**
     * @param list<ResolvedRun> $runs
     * @param float             $lineHeightMultiple applied to each run's font size
     * @return list<TextLine>
     */
    public function break(array $runs, float $maxWidthPt, float $lineHeightMultiple): array
    {
        $items = $this->tokenize($runs);
        if ($items === []) {
            return [];
        }
        $fallback = $runs[0]->style();

        /** @var list<TextLine> $lines */
        $lines = [];
        /** @var list<InlineItem> $current */
        $current = [];
        $lineWidth = 0.0;
        $lastSpace = -1;
        $widthAtLastSpace = 0.0;

        $i = 0;
        $count = count($items);
        $guard = 0;

        while ($i < $count) {
            if (++$guard > self::MAX_ITERATIONS) {
                break;
            }
            $item = $items[$i];

            if ($item instanceof BreakItem) {
                $lines[] = $this->emitLine($current, true, $fallback, $lineHeightMultiple);
                $current = [];
                $lineWidth = 0.0;
                $lastSpace = -1;
                $i++;
                continue;
            }

            if ($item instanceof SpaceItem) {
                if ($current === []) {
                    $i++; // drop leading space
                    continue;
                }
                $widthAtLastSpace = $lineWidth;
                $current[] = $item;
                $lastSpace = count($current) - 1;
                $lineWidth += $item->widthPt;
                $i++;
                continue;
            }

            // WordItem or BoxItem.
            $width = $item->widthPt();

            if ($current === []) {
                if ($width > $maxWidthPt + self::EPSILON && $item instanceof WordItem) {
                    [$head, $tail] = $item->splitAt($maxWidthPt);
                    $lines[] = $this->emitLine([$head], true, $fallback, $lineHeightMultiple);
                    if ($tail === null) {
                        $i++;
                    } else {
                        $items[$i] = $tail;
                    }
                    continue;
                }
                $current[] = $item;
                $lineWidth += $width;
                $i++;
                continue;
            }

            if ($lineWidth + $width <= $maxWidthPt + self::EPSILON) {
                $current[] = $item;
                $lineWidth += $width;
                $i++;
                continue;
            }

            // Does not fit — break the line.
            if ($lastSpace >= 0) {
                $before = array_slice($current, 0, $lastSpace);
                $lines[] = $this->emitLine($before, false, $fallback, $lineHeightMultiple, $widthAtLastSpace);
                $current = array_slice($current, $lastSpace + 1);
                $lineWidth = $this->widthOf($current);
                $lastSpace = -1;
                continue;
            }

            // No break opportunity on the line: everything on it plus $item is
            // one unbreakable token spanning runs — hard-break it mid-word to
            // fill the line, as MultiCell does (fpdf.php:732-742).
            if ($item instanceof WordItem && $maxWidthPt - $lineWidth > self::EPSILON) {
                [$head, $tail] = $item->splitAt($maxWidthPt - $lineWidth);
                $current[] = $head;
                $lines[] = $this->emitLine($current, true, $fallback, $lineHeightMultiple);
                $current = [];
                $lineWidth = 0.0;
                $lastSpace = -1;
                if ($tail === null) {
                    $i++;
                } else {
                    $items[$i] = $tail;
                }
                continue;
            }

            $lines[] = $this->emitLine($current, false, $fallback, $lineHeightMultiple);
            $current = [];
            $lineWidth = 0.0;
            $lastSpace = -1;
            // Reprocess $item against the empty line.
        }

        if ($current !== []) {
            $lines[] = $this->emitLine($current, true, $fallback, $lineHeightMultiple);
        }

        return $lines;
    }

    /**
     * @param list<ResolvedRun> $runs
     * @return list<InlineItem>
     */
    private function tokenize(array $runs): array
    {
        /** @var list<InlineItem> $items */
        $items = [];

        foreach ($runs as $run) {
            $style = $run->style();

            if ($run->isImage()) {
                $items[] = new BoxItem(
                    imageIndex: (int) $run->imageIndex,
                    widthPt: $run->imageWidthPt,
                    heightPt: $run->imageHeightPt,
                    ascentPt: $run->imageHeightPt,
                    fontSizePt: $run->fontSizePt,
                    link: $run->link,
                    baselineShiftPt: $run->baselineShiftPt,
                );
                continue;
            }

            $text = $run->text;
            $length = strlen($text);
            $wordStart = null;

            for ($k = 0; $k < $length; $k++) {
                $ch = $text[$k];
                if ($ch === "\n" || $ch === ' ') {
                    if ($wordStart !== null) {
                        $items[] = WordItem::of(substr($text, $wordStart, $k - $wordStart), $style);
                        $wordStart = null;
                    }
                    $items[] = $ch === "\n" ? new BreakItem() : SpaceItem::of($style);
                } elseif ($wordStart === null) {
                    $wordStart = $k;
                }
            }
            if ($wordStart !== null) {
                $items[] = WordItem::of(substr($text, $wordStart), $style);
            }
        }

        return $items;
    }

    /** @param list<InlineItem> $items */
    private function widthOf(array $items): float
    {
        $width = 0.0;
        foreach ($items as $item) {
            $width += $item->widthPt();
        }

        return $width;
    }

    /**
     * @param list<InlineItem> $items
     */
    private function emitLine(
        array $items,
        bool $isBreakLine,
        RunStyle $fallback,
        float $lineHeightMultiple,
        ?float $naturalWidthOverride = null,
    ): TextLine {
        while ($items !== [] && $items[count($items) - 1] instanceof SpaceItem) {
            array_pop($items);
        }

        if ($items === []) {
            $height = $fallback->fontSizePt * $lineHeightMultiple;

            return new TextLine([], 0.0, $height, 0.5 * $height + 0.3 * $fallback->fontSizePt, 0, $isBreakLine);
        }

        // Coalesce runs of Word/Space items that share a style into text
        // fragments; a BoxItem is its own fragment.
        /** @var list<array{style: ?RunStyle, text: string, box: ?BoxItem}> $groups */
        $groups = [];
        $spaceCount = 0;
        $maxFontSize = 0.0;
        $maxBoxHeight = 0.0;
        $maxBoxAscent = 0.0;

        foreach ($items as $item) {
            if ($item instanceof BreakItem) {
                continue;
            }
            if ($item instanceof BoxItem) {
                $groups[] = ['style' => null, 'text' => '', 'box' => $item];
                $maxBoxHeight = max($maxBoxHeight, $item->heightPt);
                $maxBoxAscent = max($maxBoxAscent, $item->ascentPt);
                continue;
            }

            if (!$item instanceof WordItem && !$item instanceof SpaceItem) {
                continue;
            }

            $style = $item->style;
            $text = $item instanceof SpaceItem ? ' ' : $item->text;
            $maxFontSize = max($maxFontSize, $style->fontSizePt);
            if ($item instanceof SpaceItem) {
                $spaceCount++;
            }

            $last = $groups === [] ? null : $groups[count($groups) - 1];
            if ($last !== null && $last['style'] === $style) {
                $groups[count($groups) - 1]['text'] .= $text;
            } else {
                $groups[] = ['style' => $style, 'text' => $text, 'box' => null];
            }
        }

        /** @var list<LineFragment> $fragments */
        $fragments = [];
        foreach ($groups as $group) {
            if ($group['box'] !== null) {
                $box = $group['box'];
                $fragments[] = new LineFragment(
                    text: '',
                    font: null,
                    fontSizePt: $box->fontSizePt,
                    color: Color::black(),
                    widthPt: $box->widthPt,
                    link: $box->link,
                    baselineShiftPt: $box->baselineShiftPt,
                    imageIndex: $box->imageIndex,
                    imageHeightPt: $box->heightPt,
                );
                continue;
            }

            $style = $group['style'];
            if ($style === null || $group['text'] === '') {
                continue;
            }
            $fragments[] = new LineFragment(
                text: $group['text'],
                font: $style->font,
                fontSizePt: $style->fontSizePt,
                color: $style->color,
                widthPt: $style->widthOf($group['text']),
                link: $style->link,
                underline: $style->underline,
                strikethrough: $style->strikethrough,
                baselineShiftPt: $style->baselineShiftPt,
            );
        }

        $naturalWidth = 0.0;
        foreach ($fragments as $fragment) {
            $naturalWidth += $fragment->widthPt;
        }
        if ($naturalWidthOverride !== null) {
            $naturalWidth = $naturalWidthOverride;
        }

        if ($maxFontSize === 0.0) {
            $maxFontSize = $fallback->fontSizePt;
        }

        $textHeight = $maxFontSize * $lineHeightMultiple;
        $textAscent = 0.5 * $textHeight + 0.3 * $maxFontSize;

        $height = max($textHeight, $maxBoxHeight * 1.1);
        $ascent = max($textAscent, $maxBoxAscent);

        return new TextLine(
            fragments: $fragments,
            naturalWidthPt: $naturalWidth,
            heightPt: $height,
            ascentPt: $ascent,
            justifiableGaps: $spaceCount,
            isBreakLine: $isBreakLine,
        );
    }
}
