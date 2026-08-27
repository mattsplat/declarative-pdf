<?php

declare(strict_types=1);

namespace Pdf\Layout;

/**
 * One laid-out visual line: its fragments, height, and the ascent to its
 * baseline (both in points).
 */
final readonly class TextLine
{
    /** @param list<LineFragment> $fragments */
    public function __construct(
        public array $fragments,
        public float $naturalWidthPt,
        public float $heightPt,
        public float $ascentPt,
        /** Number of inter-word gaps eligible for justification. */
        public int $justifiableGaps,
        /** True for the paragraph's final line and any hard-broken line. */
        public bool $isBreakLine,
    ) {
    }

    public function isEmpty(): bool
    {
        return $this->fragments === [];
    }
}
