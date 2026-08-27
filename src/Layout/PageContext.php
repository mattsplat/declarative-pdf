<?php

declare(strict_types=1);

namespace Pdf\Layout;

/**
 * Passed to a page's header/footer closure so it can render page numbers etc.
 *
 * Replaces the instance state (`PageNo()`, the `{nb}` alias) that FPDF's
 * `Header()` / `Footer()` overrides read (fpdf.php:356-364, 258).
 */
final readonly class PageContext
{
    public function __construct(
        public int $pageNumber,
        public int $pageCount,
        public float $contentWidthPt,
    ) {
    }
}
