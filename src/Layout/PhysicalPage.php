<?php

declare(strict_types=1);

namespace Pdf\Layout;

use Pdf\Geometry\PageGeometry;
use Pdf\Layout\Box\StackBox;
use Pdf\Node\Transition;
use Pdf\Node\Watermark;

/**
 * One rendered sheet: geometry plus positioned body / header / footer boxes.
 *
 * A logical page's `transition` and `autoAdvanceSec` are copied onto every
 * physical sheet it flows across, so the effect replays on each sheet.
 */
final readonly class PhysicalPage
{
    /**
     * @param list<PlacedArea> $areas absolutely-positioned areas (first sheet only)
     */
    public function __construct(
        public PageGeometry $geometry,
        public StackBox $body,
        public float $bodyTopPt,
        public ?StackBox $header,
        public float $headerTopPt,
        public ?StackBox $footer,
        public float $footerTopPt,
        public bool $bodyOverflowed,
        public array $areas = [],
        public ?Watermark $watermark = null,
        public ?Transition $transition = null,
        public ?float $autoAdvanceSec = null,
    ) {
    }
}
