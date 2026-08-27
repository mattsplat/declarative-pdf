<?php

declare(strict_types=1);

namespace Pdf\Node;

use Pdf\Geometry\BoxAlign;
use Pdf\Geometry\Fit;
use Pdf\Geometry\Rect;
use Pdf\Node\Placement\PlacementContent;

/**
 * An absolutely-positioned area on a page. Its rectangle is in points, in
 * from-top page coordinates. Placements render on the first physical sheet of
 * their logical page, over the flow content.
 *
 * There is no FPDF equivalent — this is the "lay out a big sheet in regions"
 * mode.
 */
final readonly class Placement
{
    public function __construct(
        public Rect $rectPt,
        public PlacementContent $content,
        public Fit $fit = Fit::Contain,
        public BoxAlign $align = BoxAlign::Center,
    ) {
    }
}
