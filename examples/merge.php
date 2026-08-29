<?php

declare(strict_types=1);

/**
 * Merge several PDFs into one by stamping every source page onto a new page of
 * the same size.
 *
 * The built-in importer is single-page: each imported page becomes a **vector
 * Form XObject** placed full-bleed on a fresh page. That copies the visual
 * content faithfully but **drops** the sources' links, bookmarks / outlines,
 * form fields, annotations, named destinations and page labels. Embedded fonts
 * and images are deduplicated within each source file, not across files.
 * Encrypted sources are rejected.
 *
 * For a merge that preserves all of the above, shell out instead:
 *
 *   qpdf --empty --pages a.pdf b.pdf c.pdf -- merged.pdf
 */

require __DIR__ . '/../vendor/autoload.php';

use Pdf\Document;
use Pdf\Geometry\BoxAlign;
use Pdf\Geometry\Fit;
use Pdf\Geometry\Orientation;
use Pdf\Geometry\PageSize;
use Pdf\Geometry\Unit;
use Pdf\Import\PdfImportDocument;
use Pdf\Style\StylePatch;

// --- stand-in sources (you would pass real files) --------------------------

$tmp = sys_get_temp_dir();
$sources = [];

$sources[] = "{$tmp}/merge-cover.pdf";
Document::create()
    ->page(fn ($p) => $p
        ->size(PageSize::letter())
        ->heading(1, 'Quarterly Review')
        ->paragraph('Cover page — generated separately, then merged.'))
    ->save($sources[0]);

$sources[] = "{$tmp}/merge-body.pdf";
Document::create()
    ->page(fn ($p) => $p
        ->heading(2, 'Section 1')
        ->paragraph(str_repeat('Body copy that wraps and paginates. ', 40)))
    ->page(fn ($p) => $p
        ->heading(2, 'Section 2')
        ->paragraph(str_repeat('More body copy on a second page. ', 25)))
    ->save($sources[1]);

$sources[] = "{$tmp}/merge-appendix.pdf";
Document::create()
    ->page(fn ($p) => $p
        ->size(PageSize::a4())->landscape()
        ->heading(2, 'Appendix — wide table')
        ->paragraph('A landscape page; the merge keeps each source page at its own size.',
            new StylePatch(spaceAfterPt: 6)))
    ->save($sources[2]);

// --- the merge ----------------------------------------------------------

$merged = Document::create();

foreach ($sources as $path) {
    $source = PdfImportDocument::fromFile($path);

    for ($n = 1; $n <= $source->pageCount(); $n++) {
        $page = $source->page($n);
        $w = $page->widthPt();
        $h = $page->heightPt();

        $merged->page(fn ($p) => $p
            ->size(PageSize::fromUnits($w, $h, Unit::Pt))
            // a page size is normalised by its orientation, so set it to match
            ->orientation($w >= $h ? Orientation::Landscape : Orientation::Portrait)
            ->units(Unit::Pt)
            ->margin(0)
            ->placePdf(0, 0, $w, $h, $path, $n, Fit::Contain, BoxAlign::Center));
    }
}

$merged->save(__DIR__ . '/merge.pdf');

foreach ($sources as $path) {
    @unlink($path);
}

echo 'Wrote ' . __DIR__ . "/merge.pdf\n";
