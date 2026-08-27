<?php

declare(strict_types=1);

namespace Pdf\Layout;

/**
 * A measured, splittable unit of vertical content.
 *
 * Heights are in points and exclude the box's own vertical margins, which are
 * reported separately so a {@see StackBox} can collapse them.
 */
interface Box
{
    public function contentHeightPt(): float;

    public function marginBeforePt(): float;

    public function marginAfterPt(): float;

    /** The following sibling should stay on the same page when possible. */
    public function keepWithNext(): bool;

    /** This box must never be split across a page boundary. */
    public function keepTogether(): bool;

    /** A descendant forces a page break that pagination must surface. */
    public function hasForcedBreak(): bool;

    /**
     * Divide the box so the head's content fits within $availableHeightPt.
     *
     * @return array{0: ?Box, 1: ?Box} [head, tail]
     *   head === null  -> nothing fits; move the whole box to the next page
     *   tail === null  -> the box fits entirely
     */
    public function split(float $availableHeightPt): array;

    /** Draw the box with the top of its content at ($xPt, $yTopPt). */
    public function render(Canvas $canvas, float $xPt, float $yTopPt, float $widthPt): void;

    /**
     * Narrowest width the box can take without content overflowing — the
     * widest unbreakable token. Used by table column autosizing.
     */
    public function minIntrinsicWidthPt(): float;

    /**
     * Width the box wants if never forced to wrap — its natural single-line
     * width. Used by table column autosizing.
     */
    public function maxIntrinsicWidthPt(): float;
}
