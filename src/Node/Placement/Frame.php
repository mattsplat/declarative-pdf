<?php

declare(strict_types=1);

namespace Pdf\Node\Placement;

use Pdf\Color\Color;
use Pdf\Style\Border;

/**
 * A bordered and/or filled rectangle drawn exactly at the area's bounds —
 * sheet borders, viewport outlines, title-block cells.
 */
final readonly class Frame implements PlacementContent
{
    public function __construct(
        public Border $border = new Border(),
        public ?Color $background = null,
    ) {
    }
}
