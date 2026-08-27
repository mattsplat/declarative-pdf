<?php

declare(strict_types=1);

namespace Pdf\Layout\Box;

use Pdf\Geometry\Edges;
use Pdf\Layout\Canvas;
use Pdf\Style\Border;
use Pdf\Style\Style;

/**
 * A box with padding, an optional border and an optional background around a
 * nested {@see StackBox}.
 *
 * Also serves as a plain margin wrapper (zero padding, no border/background)
 * for blocks whose only "box" property is outer spacing, such as lists.
 *
 * When split, the head keeps the top edge and the tail keeps the bottom edge,
 * mirroring how `MultiCell()` moved its `T`/`B` border flags between fragments
 * (fpdf.php:675-719).
 */
final class ContainerBox extends AbstractBox
{
    private const EPSILON = 1e-4;

    public function __construct(
        private readonly StackBox $inner,
        private readonly Edges $paddingPt,
        private readonly Border $border,
        private readonly ?\Pdf\Color\Color $background,
        private readonly Style $style,
        private readonly bool $keepMarginBefore = true,
        private readonly bool $keepMarginAfter = true,
        private readonly bool $suppressTopEdge = false,
        private readonly bool $suppressBottomEdge = false,
    ) {
    }

    private function effectiveBorder(): Border
    {
        $b = $this->border;
        if ($this->suppressTopEdge) {
            $b = $b->withoutTop();
        }
        if ($this->suppressBottomEdge) {
            $b = $b->withoutBottom();
        }

        return $b;
    }

    private function topInsetPt(): float
    {
        $b = $this->effectiveBorder();

        return $b->widthPt->top + ($this->suppressTopEdge ? 0.0 : $this->paddingPt->top);
    }

    private function bottomInsetPt(): float
    {
        $b = $this->effectiveBorder();

        return $b->widthPt->bottom + ($this->suppressBottomEdge ? 0.0 : $this->paddingPt->bottom);
    }

    private function horizontalInsetPt(): float
    {
        return $this->border->widthPt->horizontal() + $this->paddingPt->horizontal();
    }

    public function contentHeightPt(): float
    {
        return $this->topInsetPt() + $this->inner->contentHeightPt() + $this->bottomInsetPt();
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

    public function hasForcedBreak(): bool
    {
        return $this->inner->hasForcedBreak();
    }

    public function split(float $availableHeightPt): array
    {
        if (
            $this->contentHeightPt() <= $availableHeightPt + self::EPSILON
            && !$this->inner->hasForcedBreak()
        ) {
            return [$this, null];
        }
        if ($this->style->keepTogether) {
            return [null, $this];
        }

        // Give the inner stack room for its content (a forced break inside is
        // honoured regardless of the height passed).
        $innerAvailable = $availableHeightPt - $this->topInsetPt();
        [$innerHead, $innerTail] = $this->inner->split(max(0.0, $innerAvailable));

        if ($innerHead === null) {
            return [null, $this];
        }

        $head = new self(
            $innerHead,
            $this->paddingPt,
            $this->border,
            $this->background,
            $this->style,
            keepMarginBefore: $this->keepMarginBefore,
            keepMarginAfter: false,
            suppressTopEdge: $this->suppressTopEdge,
            suppressBottomEdge: true,
        );

        $tail = new self(
            $innerTail ?? new StackBox([]),
            $this->paddingPt,
            $this->border,
            $this->background,
            $this->style,
            keepMarginBefore: false,
            keepMarginAfter: $this->keepMarginAfter,
            suppressTopEdge: true,
            suppressBottomEdge: $this->suppressBottomEdge,
        );

        return [$head, $tail];
    }

    public function render(Canvas $canvas, float $xPt, float $yTopPt, float $widthPt): void
    {
        $height = $this->contentHeightPt();

        if ($this->background !== null) {
            $canvas->fillRect($xPt, $yTopPt, $widthPt, $height, $this->background);
        }

        $effectiveBorder = $this->effectiveBorder();
        if ($effectiveBorder->isVisible()) {
            $canvas->strokeEdges($xPt, $yTopPt, $widthPt, $height, $effectiveBorder->widthPt, $effectiveBorder->color);
        }

        $this->inner->render(
            $canvas,
            $xPt + $this->border->widthPt->left + $this->paddingPt->left,
            $yTopPt + $this->topInsetPt(),
            $widthPt - $this->horizontalInsetPt(),
        );
    }

    public function minIntrinsicWidthPt(): float
    {
        return $this->inner->minIntrinsicWidthPt() + $this->horizontalInsetPt();
    }

    public function maxIntrinsicWidthPt(): float
    {
        return $this->inner->maxIntrinsicWidthPt() + $this->horizontalInsetPt();
    }

    public function firstLineAscentPt(): ?float
    {
        return $this->inner->firstLineAscentPt();
    }
}
