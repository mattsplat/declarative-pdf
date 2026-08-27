<?php

declare(strict_types=1);

namespace Pdf\Layout;

/**
 * Where a named destination anchor landed on a page (top-left user space Y).
 */
final readonly class AnchorMark
{
    public function __construct(
        public string $name,
        public float $yTopPt,
    ) {
    }
}
