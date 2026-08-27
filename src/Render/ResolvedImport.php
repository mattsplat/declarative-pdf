<?php

declare(strict_types=1);

namespace Pdf\Render;

use Pdf\Import\ImportedPage;

/** A resolved imported PDF page with a resource index (`/Import{index}`). */
final class ResolvedImport
{
    public ?int $objectNumber = null;

    public function __construct(
        public readonly int $index,
        public readonly ImportedPage $page,
    ) {
    }
}
