<?php

declare(strict_types=1);

namespace Pdf\Layout;

use Pdf\Exception\PdfException;

/**
 * One row or column track of a {@see Grid}: either a fixed size in points or a
 * share of the leftover space by weight (like CSS `fr`).
 *
 * Fixed tracks are subtracted first; whatever remains after the gutters is
 * divided among the fractional tracks in proportion to their weight.
 */
final readonly class Track
{
    private function __construct(
        public float $value,
        public bool $isFraction,
    ) {
    }

    public static function fr(float $weight = 1.0): self
    {
        if ($weight <= 0.0) {
            throw new PdfException('Track fraction weight must be positive.');
        }

        return new self($weight, true);
    }

    public static function pt(float $points): self
    {
        if ($points < 0.0) {
            throw new PdfException('Track size must not be negative.');
        }

        return new self($points, false);
    }
}
