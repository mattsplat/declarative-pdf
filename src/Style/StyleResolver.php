<?php

declare(strict_types=1);

namespace Pdf\Style;

use Pdf\Font\FontStyle;
use Pdf\Node\BlockNode;
use Pdf\Node\BulletList;
use Pdf\Node\Clip;
use Pdf\Node\Container;
use Pdf\Node\Heading;
use Pdf\Node\OrderedList;
use Pdf\Node\Paragraph;
use Pdf\Node\Table;

/**
 * Turns the sparse per-node patches into a fully-resolved {@see Style} for each
 * node: inheriting properties flow from the parent, non-inheriting ones start
 * from their defaults, then built-in defaults for headings/paragraphs and
 * finally the node's own patch are applied.
 *
 * FPDF has no equivalent — style was whatever `SetFont()` / `SetTextColor()`
 * last left in the instance state.
 */
final class StyleResolver
{
    /** Font-size multipliers for h1..h6, relative to the base size. */
    private const HEADING_SCALE = [1 => 2.0, 2 => 1.5, 3 => 1.17, 4 => 1.0, 5 => 0.83, 6 => 0.75];

    public function __construct(
        private readonly ?Stylesheet $stylesheet = null,
        /** Multiplier applied to every hard-coded `fontSizePt` a patch introduces. */
        private readonly float $fontScale = 1.0,
    ) {
    }

    /**
     * A clone whose resolved styles have every hard-coded `fontSizePt` from a
     * patch multiplied by $scale. Inherited sizes already shrink through the
     * (pre-scaled) parent style; this is what catches an absolute override such
     * as `StylePatch(fontSizePt: 9)` that the parent chain would ignore. Used
     * by {@see \Pdf\Node\Placement\Blocks} shrink-to-fit; the shared resolver is
     * never mutated.
     */
    public function withFontScale(float $scale): self
    {
        return new self($this->stylesheet, $this->fontScale * $scale);
    }

    public function resolveBlock(BlockNode $node, Style $parent): Style
    {
        $style = $parent->resetBlockProperties();

        if ($node instanceof Heading) {
            $style = $this->headingDefaults($node->level, $style->fontSizePt)->applyTo($style);
        } elseif ($node instanceof Paragraph) {
            $style = $this->paragraphDefaults()->applyTo($style);
        }

        if ($this->stylesheet !== null) {
            $sheetPatch = $this->stylesheet->patchFor(...$this->selectorsFor($node));
            $style = $this->scaleFixedFontSize($sheetPatch, $sheetPatch->applyTo($style));
        }

        $patch = $node->patch();

        return $this->scaleFixedFontSize($patch, $patch->applyTo($style));
    }

    /**
     * The node-type selector followed by the node's own class names (each
     * `.`-prefixed to match {@see Stylesheet::class()}'s namespaced keys), so a
     * class rule wins over the type rule and a later class wins over an earlier
     * one.
     *
     * @return list<string>
     */
    private function selectorsFor(BlockNode $node): array
    {
        $selectors = match (true) {
            $node instanceof Heading => ['h' . $node->level],
            $node instanceof Paragraph => ['paragraph'],
            $node instanceof BulletList, $node instanceof OrderedList => ['list'],
            $node instanceof Table => ['table'],
            $node instanceof Container => ['container'],
            $node instanceof Clip => ['clip'],
            default => [],
        };

        $class = $node->patch()->class;
        if ($class !== null) {
            foreach (preg_split('/\s+/', trim($class)) ?: [] as $name) {
                if ($name !== '') {
                    $selectors[] = '.' . $name;
                }
            }
        }

        return $selectors;
    }

    /** Resolve an inline run's style on top of its block's resolved style. */
    public function resolveInline(StylePatch $patch, Style $block): Style
    {
        return $this->scaleFixedFontSize($patch, $patch->applyTo($block));
    }

    /**
     * When a font scale is in effect, re-apply it to a size the patch pinned
     * absolutely (`fontSizePt: 9`) — the parent chain carries the scale for
     * inherited and relatively-scaled sizes, but not for a hard-coded one.
     */
    private function scaleFixedFontSize(StylePatch $patch, Style $resolved): Style
    {
        if ($this->fontScale === 1.0 || $patch->fontSizePt === null) {
            return $resolved;
        }

        return (new StylePatch(fontSizePt: $resolved->fontSizePt * $this->fontScale))->applyTo($resolved);
    }

    private function headingDefaults(int $level, float $baseSizePt): StylePatch
    {
        $size = $baseSizePt * self::HEADING_SCALE[$level];

        return new StylePatch(
            fontStyle: FontStyle::Bold,
            fontSizePt: $size,
            lineHeight: 1.2,
            spaceBeforePt: $size * 0.6,
            spaceAfterPt: $size * 0.35,
            keepWithNext: true,
        );
    }

    private function paragraphDefaults(): StylePatch
    {
        return new StylePatch(spaceAfterPt: 6.0);
    }
}
