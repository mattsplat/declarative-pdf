<?php

declare(strict_types=1);

namespace Pdf\Render;

use Pdf\Geometry\PageGeometry;
use Pdf\Layout\WidgetRect;

/**
 * One `/Subtype /Widget` annotation with its object number and the object
 * numbers of its `/AP /N` appearance state(s), resolved by
 * {@see AcroFormWriter} before the page objects are written.
 */
final readonly class PlannedWidget
{
    /**
     * @param array<string, int> $appearances state name (`N`, `Off`, an export value) => XObject object number
     */
    public function __construct(
        public int $objectNumber,
        public int $pageIndex,
        public int $pageObject,
        public PageGeometry $geometry,
        public WidgetRect $rect,
        public array $appearances,
    ) {
    }
}
