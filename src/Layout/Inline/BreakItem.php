<?php

declare(strict_types=1);

namespace Pdf\Layout\Inline;

/**
 * A hard line break (`\n` or `<br>`) — ends the line without ending the block.
 */
final readonly class BreakItem implements InlineItem
{
    public function widthPt(): float
    {
        return 0.0;
    }
}
