<?php

declare(strict_types=1);

namespace Pdf\Layout\Box;

use Pdf\Layout\Box;

/**
 * Sensible defaults for the pagination hints so concrete boxes only override
 * what they need.
 */
abstract class AbstractBox implements Box
{
    public function marginBeforePt(): float
    {
        return 0.0;
    }

    public function marginAfterPt(): float
    {
        return 0.0;
    }

    public function keepWithNext(): bool
    {
        return false;
    }

    public function keepTogether(): bool
    {
        return false;
    }

    public function hasForcedBreak(): bool
    {
        return false;
    }

    public function minIntrinsicWidthPt(): float
    {
        return 0.0;
    }

    public function maxIntrinsicWidthPt(): float
    {
        return 0.0;
    }
}
