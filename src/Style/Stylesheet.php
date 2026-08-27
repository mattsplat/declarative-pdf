<?php

declare(strict_types=1);

namespace Pdf\Style;

/**
 * Named style rules applied by {@see StyleResolver} between a block's built-in
 * defaults and its own patch.
 *
 * Selectors are node-type names: `h1`..`h6`, `paragraph`, `list`, `table`,
 * `container`. FPDF had no equivalent — every style was ad-hoc `SetFont()` /
 * `SetTextColor()` calls.
 */
final class Stylesheet
{
    /** @var array<string, StylePatch> */
    private array $rules = [];

    public function set(string $selector, StylePatch $patch): self
    {
        $this->rules[strtolower($selector)] = $patch;

        return $this;
    }

    public function heading(int $level, StylePatch $patch): self
    {
        return $this->set('h' . $level, $patch);
    }

    public function paragraph(StylePatch $patch): self
    {
        return $this->set('paragraph', $patch);
    }

    public function get(string $selector): ?StylePatch
    {
        return $this->rules[strtolower($selector)] ?? null;
    }

    /** Merge the patches for the given selectors, later selectors winning. */
    public function patchFor(string ...$selectors): StylePatch
    {
        $merged = new StylePatch();
        $found = false;
        foreach ($selectors as $selector) {
            $patch = $this->get($selector);
            if ($patch !== null) {
                $merged = self::merge($merged, $patch);
                $found = true;
            }
        }

        return $found ? $merged : new StylePatch();
    }

    private static function merge(StylePatch $base, StylePatch $over): StylePatch
    {
        return new StylePatch(
            fontFamily: $over->fontFamily ?? $base->fontFamily,
            fontStyle: $over->fontStyle ?? $base->fontStyle,
            bold: $over->bold ?? $base->bold,
            italic: $over->italic ?? $base->italic,
            fontSizePt: $over->fontSizePt ?? $base->fontSizePt,
            color: $over->color ?? $base->color,
            align: $over->align ?? $base->align,
            lineHeight: $over->lineHeight ?? $base->lineHeight,
            spaceBeforePt: $over->spaceBeforePt ?? $base->spaceBeforePt,
            spaceAfterPt: $over->spaceAfterPt ?? $base->spaceAfterPt,
            paddingPt: $over->paddingPt ?? $base->paddingPt,
            border: $over->border ?? $base->border,
            background: $over->background ?? $base->background,
            underline: $over->underline ?? $base->underline,
            strikethrough: $over->strikethrough ?? $base->strikethrough,
            fontSizeScale: $over->fontSizeScale ?? $base->fontSizeScale,
            baselineShift: $over->baselineShift ?? $base->baselineShift,
            keepWithNext: $over->keepWithNext ?? $base->keepWithNext,
            keepTogether: $over->keepTogether ?? $base->keepTogether,
            orphans: $over->orphans ?? $base->orphans,
            widows: $over->widows ?? $base->widows,
        );
    }
}
