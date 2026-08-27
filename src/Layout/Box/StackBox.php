<?php

declare(strict_types=1);

namespace Pdf\Layout\Box;

use Pdf\Layout\Box;
use Pdf\Layout\Canvas;

/**
 * A vertical stack of boxes with collapsing margins.
 *
 * `split()` is the heart of pagination: it walks children top-down, places
 * those that fit, splits the first one that doesn't (unless it is
 * `keepTogether`), and applies `keepWithNext` by pulling trailing "kept"
 * boxes onto the next page. Replaces the manual `AddPage()` / `AcceptPageBreak()`
 * dance (fpdf.php:287, 574, 584).
 */
final class StackBox extends AbstractBox
{
    private const EPSILON = 1e-4;

    /** @param list<Box> $children */
    public function __construct(private readonly array $children)
    {
    }

    /** @return list<Box> */
    public function children(): array
    {
        return $this->children;
    }

    public function isEmpty(): bool
    {
        foreach ($this->children as $child) {
            if (!$child instanceof PageBreakBox) {
                return false;
            }
        }

        return true;
    }

    public function first(): ?Box
    {
        foreach ($this->children as $child) {
            if (!$child instanceof PageBreakBox) {
                return $child;
            }
        }

        return null;
    }

    public function hasForcedBreak(): bool
    {
        foreach ($this->children as $child) {
            if ($child->hasForcedBreak()) {
                return true;
            }
        }

        return false;
    }

    public function withoutFirst(): self
    {
        $seen = false;
        $rest = [];
        foreach ($this->children as $child) {
            if (!$seen && !$child instanceof PageBreakBox) {
                $seen = true;
                continue;
            }
            if ($seen) {
                $rest[] = $child;
            }
        }

        return new self($rest);
    }

    /** @return list<Box> */
    private function contentChildren(): array
    {
        return array_values(array_filter(
            $this->children,
            static fn (Box $b) => !$b instanceof PageBreakBox,
        ));
    }

    public function contentHeightPt(): float
    {
        $height = 0.0;
        $previousAfter = 0.0;
        $first = true;
        foreach ($this->contentChildren() as $child) {
            $height += ($first ? 0.0 : max($previousAfter, $child->marginBeforePt())) + $child->contentHeightPt();
            $previousAfter = $child->marginAfterPt();
            $first = false;
        }

        return $height;
    }

    public function marginBeforePt(): float
    {
        return $this->first()?->marginBeforePt() ?? 0.0;
    }

    public function marginAfterPt(): float
    {
        $content = $this->contentChildren();
        $last = end($content);

        return $last instanceof Box ? $last->marginAfterPt() : 0.0;
    }

    public function keepWithNext(): bool
    {
        $content = $this->contentChildren();
        $last = end($content);

        return $last instanceof Box && $last->keepWithNext();
    }

    /**
     * @return array{0: ?StackBox, 1: ?StackBox}
     */
    public function split(float $availableHeightPt): array
    {
        /** @var list<Box> $placed */
        $placed = [];
        $cursorY = 0.0;
        $previousAfter = 0.0;
        $first = true;
        $n = count($this->children);

        for ($i = 0; $i < $n; $i++) {
            $child = $this->children[$i];

            if ($child instanceof PageBreakBox) {
                return [
                    $placed === [] ? null : new self($placed),
                    $this->tailFrom($i + 1),
                ];
            }

            $gap = $first ? 0.0 : max($previousAfter, $child->marginBeforePt());

            $forcesBreak = $child->hasForcedBreak();

            if (
                !$forcesBreak
                && $cursorY + $gap + $child->contentHeightPt() <= $availableHeightPt + self::EPSILON
            ) {
                $placed[] = $child;
                $cursorY += $gap + $child->contentHeightPt();
                $previousAfter = $child->marginAfterPt();
                $first = false;
                continue;
            }

            // The child does not fully fit, or it contains a forced break.
            if ($child->keepTogether() && !$forcesBreak) {
                $head = null;
                $tail = $child;
            } else {
                [$head, $tail] = $child->split(max(0.0, $availableHeightPt - $cursorY - $gap));
            }

            if ($head !== null) {
                $placed[] = $head;
                $tailChildren = $tail !== null ? [$tail] : [];
                $tailChildren = [...$tailChildren, ...array_slice($this->children, $i + 1)];

                return [
                    new self($placed),
                    $tailChildren === [] ? null : new self($tailChildren),
                ];
            }

            // Whole child moves to the next page; drag back kept-with-next predecessors.
            $breakAt = $i;
            while ($placed !== [] && $placed[count($placed) - 1]->keepWithNext()) {
                array_pop($placed);
                $breakAt--;
            }

            return [
                $placed === [] ? null : new self($placed),
                new self(array_slice($this->children, $breakAt)),
            ];
        }

        return [$placed === [] ? null : new self($placed), null];
    }

    private function tailFrom(int $index): ?self
    {
        $rest = array_slice($this->children, $index);

        return $rest === [] ? null : new self($rest);
    }

    public function render(Canvas $canvas, float $xPt, float $yTopPt, float $widthPt): void
    {
        $y = $yTopPt;
        $previousAfter = 0.0;
        $first = true;
        foreach ($this->contentChildren() as $child) {
            $y += $first ? 0.0 : max($previousAfter, $child->marginBeforePt());
            $child->render($canvas, $xPt, $y, $widthPt);
            $y += $child->contentHeightPt();
            $previousAfter = $child->marginAfterPt();
            $first = false;
        }
    }

    public function minIntrinsicWidthPt(): float
    {
        $min = 0.0;
        foreach ($this->contentChildren() as $child) {
            $min = max($min, $child->minIntrinsicWidthPt());
        }

        return $min;
    }

    public function maxIntrinsicWidthPt(): float
    {
        $max = 0.0;
        foreach ($this->contentChildren() as $child) {
            $max = max($max, $child->maxIntrinsicWidthPt());
        }

        return $max;
    }

    /** Ascent of the first text line anywhere in the stack, for list markers. */
    public function firstLineAscentPt(): ?float
    {
        $first = $this->first();

        return match (true) {
            $first instanceof TextBox => $first->firstLineAscentPt(),
            $first instanceof self => $first->firstLineAscentPt(),
            $first instanceof ContainerBox => $first->firstLineAscentPt(),
            default => null,
        };
    }
}
