<?php

declare(strict_types=1);

/*
 * Merge several PDFs into one by stamping every source page onto a fresh page
 * of the same size.
 *
 * The built-in importer is single-page: each imported page becomes a vector
 * Form XObject placed full-bleed on a new page. That copies the visual content
 * faithfully but DROPS the sources' links, bookmarks, form fields, annotations,
 * named destinations and page labels; fonts and images are deduplicated within
 * each source, not across sources; encrypted sources are rejected.
 *
 * For a merge that preserves all of the above, shell out instead:
 *
 *   qpdf --empty --pages a.pdf b.pdf c.pdf -- merged.pdf
 */

require __DIR__ . '/../vendor/autoload.php';

use Pdf\Chart\LegendPosition;
use Pdf\Chart\Series;
use Pdf\Color\Color;
use Pdf\Document;
use Pdf\Geometry\BoxAlign;
use Pdf\Geometry\Fit;
use Pdf\Geometry\Orientation;
use Pdf\Geometry\PageSize;
use Pdf\Geometry\Unit;
use Pdf\Import\PdfImportDocument;
use Pdf\Node\Chart;
use Pdf\Node\Paragraph;
use Pdf\Node\Table;
use Pdf\Node\TableCell;
use Pdf\Node\TableRow;
use Pdf\Style\ColumnWidth;
use Pdf\Style\StylePatch;
use Pdf\Style\TextAlign;

$navy = Color::rgb(20, 34, 66);
$tmp = sys_get_temp_dir();
$sources = [];

// --- source 1: a cover, letter portrait --------------------------------
$sources[] = "{$tmp}/merge-cover.pdf";
Document::create()
    ->meta(fn ($m) => $m->title('Quarterly Review'))
    ->page(fn ($p) => $p
        ->size(PageSize::letter())
        ->heading(1, 'Quarterly Review', new StylePatch(color: $navy, fontSizePt: 34.0))
        ->paragraph('Q3 — generated as its own document, then merged.',
            new StylePatch(color: Color::gray(110), spaceAfterPt: 16.0))
        ->chart(Chart::pie([48, 28, 24], ['Product', 'Services', 'Other'], 60.0,
            Unit::Mm, LegendPosition::Right)))
    ->save($sources[0]);

// --- source 2: a two-page body, A4 portrait --------------------------
$sources[] = "{$tmp}/merge-body.pdf";
Document::create()
    ->page(fn ($p) => $p
        ->heading(2, 'Section 1 — narrative', new StylePatch(color: $navy))
        ->paragraph(str_repeat('Body copy that wraps and paginates naturally. ', 45),
            new StylePatch(align: TextAlign::Justify)))
    ->page(fn ($p) => $p
        ->heading(2, 'Section 2 — figures', new StylePatch(color: $navy))
        ->add(new Table(
            [
                new TableRow([
                    new TableCell('Line', patch: new StylePatch(bold: true)),
                    new TableCell('Q2', patch: new StylePatch(bold: true, align: TextAlign::Right)),
                    new TableCell('Q3', patch: new StylePatch(bold: true, align: TextAlign::Right)),
                ]),
                new TableRow([new TableCell('Revenue'), new TableCell('12.4', patch: new StylePatch(align: TextAlign::Right)), new TableCell('13.9', patch: new StylePatch(align: TextAlign::Right))]),
                new TableRow([new TableCell('Cost'), new TableCell('8.1', patch: new StylePatch(align: TextAlign::Right)), new TableCell('8.6', patch: new StylePatch(align: TextAlign::Right))]),
                new TableRow([new TableCell('Margin'), new TableCell('4.3', patch: new StylePatch(align: TextAlign::Right)), new TableCell('5.3', patch: new StylePatch(align: TextAlign::Right))]),
            ],
            [ColumnWidth::fraction(1.0), ColumnWidth::fixed(60.0), ColumnWidth::fixed(60.0)],
            headerRows: 1,
        )))
    ->save($sources[1]);

// --- source 3: a landscape appendix, A4 --------------------------------
$sources[] = "{$tmp}/merge-appendix.pdf";
Document::create()
    ->page(fn ($p) => $p
        ->size(PageSize::a4())->landscape()
        ->heading(2, 'Appendix — monthly detail', new StylePatch(color: $navy))
        ->chart(Chart::bar(
            [Series::of('Revenue', [3.9, 4.2, 4.6, 4.4, 4.8, 5.2])],
            ['Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep'],
            220.0, 90.0, Unit::Mm, LegendPosition::None,
        )))
    ->save($sources[2]);

// --- the merge --------------------------------------------------------
$merged = Document::create()->meta(fn ($m) => $m->title('Merged quarterly pack'));

foreach ($sources as $path) {
    $source = PdfImportDocument::fromFile($path);

    for ($n = 1; $n <= $source->pageCount(); $n++) {
        $page = $source->page($n);
        $w = $page->widthPt();
        $h = $page->heightPt();

        $merged->page(fn ($p) => $p
            ->size(PageSize::fromUnits($w, $h, Unit::Pt))
            // a page size is normalised by its orientation, so state it explicitly
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
