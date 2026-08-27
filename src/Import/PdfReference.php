<?php

declare(strict_types=1);

namespace Pdf\Import;

/** An indirect reference (`12 0 R`). */
final readonly class PdfReference
{
    public function __construct(
        public int $number,
        public int $generation = 0,
    ) {
    }
}
