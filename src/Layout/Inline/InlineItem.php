<?php

declare(strict_types=1);

namespace Pdf\Layout\Inline;

/**
 * One atom of inline content fed to the line breaker: a word, a space, a hard
 * break, or a fixed-size box (an inline image).
 *
 * Replaces FPDF's byte-by-byte `MultiCell()` scan (fpdf.php:661-763) with an
 * item model so non-text atoms can participate in wrapping.
 */
interface InlineItem
{
    public function widthPt(): float;
}
