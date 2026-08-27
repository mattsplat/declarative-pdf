<?php

declare(strict_types=1);

namespace Pdf\Node\Placement;

use Pdf\Exception\PdfException;

/**
 * One page of an external PDF, placed into an area as a vector Form XObject
 * (stays crisp at any zoom). Source annotations, links and form fields are
 * dropped — only the page's visual content is imported.
 */
final readonly class PdfPage implements PlacementContent
{
    public function __construct(
        public string $path,
        public int $page = 1,
    ) {
        if ($page < 1) {
            throw new PdfException('PDF page numbers are 1-based.');
        }
    }
}
