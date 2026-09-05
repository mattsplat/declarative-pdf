<?php

declare(strict_types=1);

require __DIR__ . '/../vendor/autoload.php';

use Pdf\Builder\CoverLayout;
use Pdf\Builder\DataTable;
use Pdf\Builder\PageBuilder;
use Pdf\Builder\Total;
use Pdf\Chart\LegendPosition;
use Pdf\Chart\Series;
use Pdf\Color\Color;
use Pdf\Document;
use Pdf\Geometry\Unit;
use Pdf\Layout\PageContext;
use Pdf\Node\Callout;
use Pdf\Node\Card;
use Pdf\Node\Chart;
use Pdf\Node\DefinitionList;
use Pdf\Node\Paragraph;
use Pdf\Style\ColumnWidth;
use Pdf\Style\Style;
use Pdf\Style\StylePatch;
use Pdf\Style\Stylesheet;
use Pdf\Style\TextAlign;
use Pdf\Text\InlineSequence;

/*
 * One hundred pages — a pagination stress-test.
 *
 * A cover, eight chapters of flowing prose, then a ledger whose repeating
 * header walks across the rest of the document. There is one `page()` call
 * for the body: the paginator splits it. Tune LONG_LEDGER_ROWS if you
 * change the copy and the sheet count drifts.
 */

const LONG_LEDGER_ROWS = 3060;

$navy = Color::rgb(19, 33, 68);
$accent = Color::rgb(64, 120, 200);
$muted = Color::gray(115);

$base = (new StylePatch(fontSizePt: 10.5, lineHeight: 1.4, color: Color::rgb(28, 30, 36)))
    ->applyTo(Style::default());

$sheet = (new Stylesheet())
    ->heading(1, new StylePatch(color: $navy, fontSizePt: 22.0, spaceAfterPt: 4.0))
    ->heading(2, new StylePatch(color: $navy, fontSizePt: 14.0, spaceBeforePt: 12.0, spaceAfterPt: 4.0))
    ->heading(3, new StylePatch(color: Color::gray(70), fontSizePt: 11.5, spaceBeforePt: 8.0, spaceAfterPt: 3.0))
    ->paragraph(new StylePatch(align: TextAlign::Justify, spaceAfterPt: 7.0));

$chapters = long_chapters();

$doc = Document::create()
    ->meta(fn ($m) => $m
        ->title('One Hundred Pages')
        ->author('declarative-pdf')
        ->subject('Pagination stress-test')
        ->creator('examples/long.php'))
    ->baseStyle($base)
    ->stylesheet($sheet)
    ->pageNumbers('Page {n} of {N}', TextAlign::Center, 8.0, $muted)
    ->cover(fn ($c) => $c
        ->layout(CoverLayout::Centered)
        ->title('One Hundred Pages')
        ->subtitle('A pagination stress-test of the layout engine')
        ->line('declarative-pdf', '100 sheets', 'A4'))
    ->bookmark('Preface', 'preface', 0);

foreach ($chapters as $chapter) {
    $doc->bookmark($chapter['title'], $chapter['id'], 0);
}
$doc->bookmark('Appendix — block ledger', 'ledger', 0);

$doc->page(function (PageBuilder $p) use ($accent, $muted, $chapters): void {
    $p->header(fn (PageContext $c) => new Paragraph(
        'ONE HUNDRED PAGES   ·   LAYOUT STRESS TEST',
        new StylePatch(fontSizePt: 7.5, color: $muted, spaceAfterPt: 0.0),
    ));

    $p->anchor('preface');
    $p->heading(1, 'Preface');
    $p->paragraph(
        'This file is the answer to “does the paginator actually paginate?”: '
        . 'one cover, one body page, and enough blocks that the engine has to '
        . 'produce a hundred sheets. Nothing here is positioned by hand. The '
        . 'running header, the “n of N” footer and the bookmark outline are '
        . 'document-level; the cover drops the page numbers so it does not '
        . 'read “1 of 100”.',
    );
    $p->paragraph(
        'The body is a tree — headings, paragraphs, lists, callouts, charts, '
        . 'then a ledger of several thousand rows. The table repeats its header '
        . 'on every sheet it spans. Change the copy and the sheet count will '
        . 'move; the ledger row count is the knob that brings it back to 100.',
    );

    $p->component(new Callout(
        'The Y-axis flip still happens in exactly one place, every unit is '
        . 'points by the time it reaches the engine, and with a fixed clock '
        . 'the bytes are stable. A hundred pages does not change any of that.',
        title: 'Same invariants, more paper',
    ));

    $p->heading(2, 'Contents');
    $p->paragraph(
        'The outline in the viewer jumps to each chapter. The list below is '
        . 'the same structure, without page numbers — those are only known '
        . 'after pagination, which is why the footer can say “of 100” on every '
        . 'sheet including this one.',
        new StylePatch(spaceAfterPt: 8.0),
    );
    $p->orderedList(array_map(
        static fn (array $chapter): string => $chapter['title'] . ' — ' . $chapter['kicker'],
        $chapters,
    ));
    $p->paragraph('Appendix — the block ledger, one row per measured box.',
        new StylePatch(spaceBeforePt: 4.0));

    foreach ($chapters as $index => $chapter) {
        $p->pageBreak();
        long_render_chapter($p, $chapter, $index, $accent);
    }

    $p->pageBreak();
    $p->anchor('ledger');
    $p->heading(1, 'Appendix — block ledger');
    $p->paragraph(
        'Every row is a fictional box the measurer might have produced: a '
        . 'stable id, the chapter it belonged to, the node kind, the content '
        . 'width and height in points, and whether the paginator split it. '
        . 'The header row repeats for as long as the table runs. Group '
        . 'headers and subtotals are consecutive because the rows are emitted '
        . 'already sorted by chapter.',
    );

    $p->dataTable(
        DataTable::of(long_ledger(LONG_LEDGER_ROWS, $chapters))
            ->column('id', 'Id', width: ColumnWidth::fixed(70.0))
            ->column('chapter', 'Chapter')
            ->column('node', 'Node')
            ->column('width', 'W (pt)', TextAlign::Right, ColumnWidth::fixed(58.0), static fn (mixed $v): string => number_format((float) $v, 1))
            ->column('height', 'H (pt)', TextAlign::Right, ColumnWidth::fixed(58.0), static fn (mixed $v): string => number_format((float) $v, 1))
            ->column('split', 'Split', TextAlign::Right, ColumnWidth::fixed(48.0))
            ->groupBy('chapter')
            ->totals([
                'width' => Total::avg(),
                'height' => Total::sum(),
            ])
            ->headerBackground(Color::rgb(230, 236, 245))
            ->borderColor(Color::gray(205)),
    );

    $p->heading(2, 'Colophon');
    $p->paragraph(
        'Three thousand and sixty rows, eight chapter groups, one repeating '
        . 'header. The paginator stopped at sheet 100 because that is where '
        . 'the tree ended — nobody called AddPage a hundred times. The cover '
        . 'is sheet 1 and carries no page number; this sentence is the last '
        . 'block on sheet 100.',
    );
});

$doc->save(__DIR__ . '/long.pdf');

echo 'Wrote ' . __DIR__ . "/long.pdf\n";

/**
 * @return list<array{id: string, title: string, kicker: string, lead: string, body: list<string>, notes: list<string>, terms: array<string, string>}>
 */
function long_chapters(): array
{
    return [
        [
            'id' => 'measure',
            'title' => 'Measure',
            'kicker' => 'Everything is points',
            'lead' => 'User units stop at the API boundary. Millimetres, centimetres and inches convert once; the measurer, the line breaker and the writer never see them.',
            'body' => [
                'A block is asked for its natural height at a given width. Paragraphs walk their inline runs, tables ask each cell, images report an intrinsic ratio, charts occupy a fixed box. The result is a stack of boxes with content, padding, border and margin — the same model CSS uses, without a layout viewport.',
                'Keep-together and keep-with-next are measured here too. A heading that cannot sit with the paragraph beneath it is not a pagination surprise; it is a taller box the paginator will simply move. That is why a hundred-page run does not need a second code path.',
                'The only number that is allowed to be a user unit is the one you typed. By the time a box is asked to split, the width it is splitting against is already points, and so is the height of the hole it is being asked to fill.',
            ],
            'notes' => [
                'PageSize::a4() is 595.28 × 841.89 pt.',
                'The default margin is 10 mm, converted once in PageBuilder.',
                'PageGeometry::flipY() is the single place the Y-axis inverts.',
                'Charts and paths declare their box in points even when the factory took millimetres.',
            ],
            'terms' => [
                'contentHeightPt' => 'Natural height of a box at the available width.',
                'split' => 'Break a box into a piece that fits and a remainder.',
                'Unit' => 'Mm, Cm, In, Pt — converted at the call site.',
            ],
        ],
        [
            'id' => 'break',
            'title' => 'Line breaking',
            'kicker' => 'Greedy, then justify',
            'lead' => 'The line breaker is a greedy packer. It fills each line as far as the width allows, then, if you asked for justify, distributes the leftover across the word gaps.',
            'body' => [
                'Inline runs carry their own style — bold, italic, underline, strike, super- and subscript, a link, a hard break, an inline image. HTML is a thin parser in front of the same sequence. An unknown tag is not an error; it is literal text.',
                'Widows and orphans are a pagination concern, but they start as line counts. A paragraph that would leave a single line on either side of a break is asked to split differently. The defaults are two lines; a StylePatch can raise them.',
                'Justification never stretches the last line of a paragraph, and it never stretches a line that ends in a hard break. That matches what MultiCell did, without a cursor to keep in sync.',
            ],
            'notes' => [
                'Soft hyphens are not a feature yet; long words overflow rather than invent a break.',
                'Inline images sit on the baseline and count toward line width.',
                'A link is an annotation, not a colour — the underline is optional.',
                'Html::toInline() is the same path as PageBuilder::html().',
            ],
            'terms' => [
                'InlineSequence' => 'The typed run list a paragraph actually stores.',
                'widows / orphans' => 'Minimum lines kept on either side of a break.',
                'TextAlign::Justify' => 'Distribute leftover width across word gaps.',
            ],
        ],
        [
            'id' => 'paginate',
            'title' => 'Pagination',
            'kicker' => 'Split, then number',
            'lead' => 'The paginator fills a page, asks the overflowing box to split, and repeats. Headers and footers run after the whole document is laid out, so the total page count is real — there is no {nb} placeholder.',
            'body' => [
                'A first pass estimates band height with single-digit page numbers. A second pass reserves that height and lays the body out for real. If the footer text grows (“9 of 9” versus “10 of 10”) the reserve already had room; the extra digit does not shove content.',
                'Containers, lists and tables split. Charts, images and paths do not: they move whole to the next sheet when they do not fit. Keep-together is the same idea applied to a stack you would rather not tear.',
                'An explicit page break is just another block. It ends the current sheet even if there is room left. Chapters in this file start that way so a heading is never the last line of a page for a boring reason.',
            ],
            'notes' => [
                'MAX_PAGES is 20,000 — a hundred is not a special case.',
                'Header and footer factories receive PageContext, not instance state.',
                'A cover is a separate Page; it counts in N but can drop furniture.',
                'Overflow of a keep-together box moves the whole box, never clips it.',
            ],
            'terms' => [
                'PageContext' => 'pageNumber, pageCount, contentWidthPt.',
                'PhysicalPage' => 'One rendered sheet after pagination.',
                'PageBreak' => 'A block that forces a new sheet.',
            ],
        ],
        [
            'id' => 'tables',
            'title' => 'Tables',
            'kicker' => 'Size, then split',
            'lead' => 'Column widths are a deterministic take on CSS automatic table layout: fixed columns take their width, fraction columns share what is left, auto columns interpolate between min and max content.',
            'body' => [
                'Header rows repeat on every sheet the table spans. That is the reason the appendix of this document is readable on page 40 and page 90 alike: the column titles travel with the body. DataTable is a builder in front of the same node — group headers, subtotals, a grand total.',
                'A cell can hold blocks, not just a string. Nested paragraphs wrap inside the column; a nested table is a box like any other. Colspans are counted in grid columns, and a row that does not fit splits at a cell boundary when it can.',
                'Numeric columns in this file are right-aligned with a formatter on the way in. Totals run on the raw numbers, then the same formatter paints the sum, so a points column still reads as points in the total row.',
            ],
            'notes' => [
                'headerRows is the count of leading rows that repeat, not a boolean.',
                'groupBy() groups consecutive rows — sort first if you need that.',
                'A lone group’s subtotal is not repeated as a grand total.',
                'Border width and cell padding are points, like everything else.',
            ],
            'terms' => [
                'ColumnWidth::auto()' => 'Size from content, then flex.',
                'ColumnWidth::fixed()' => 'A width in points, no flex.',
                'ColumnWidth::fraction()' => 'A share of leftover width.',
            ],
        ],
        [
            'id' => 'style',
            'title' => 'Style',
            'kicker' => 'Sparse patches, not SetFont',
            'lead' => 'A StylePatch is a set of overrides. Anything left null is inherited. The resolver order is base style, node-type rule, class rule, then the node’s own patch, so a paragraph can always win locally.',
            'body' => [
                'The document you are reading sets a 10.5 pt body, justified, with navy headings, once, on the DocumentBuilder. Chapters do not repeat those facts. A callout, a card and a definition list opt into their own patches because they are components that expand to ordinary nodes.',
                'Colour is RGB in 0–255, or gray(), or fromHex(). There is no named-colour table. A border is four edge widths and a colour; a single non-zero edge is how the callouts get their accent bar.',
                'Keep-with-next on headings is a style, not a paginator special case. Turn it off and a heading is willing to sit at the bottom of a sheet. The default is on because that is almost always what you meant.',
            ],
            'notes' => [
                'Stylesheet selectors are h1–h6, paragraph, list, table, container, or a class name.',
                'class: on a patch is a selector, not a style — applyTo() never reads it.',
                'Font weight is 100–900; bold is shorthand for 700.',
                'spaceBefore / spaceAfter collapse between siblings the CSS way.',
            ],
            'terms' => [
                'Style' => 'The resolved, fully populated value.',
                'StylePatch' => 'The sparse override you actually write.',
                'Stylesheet' => 'Named rules applied between base and patch.',
            ],
        ],
        [
            'id' => 'fonts',
            'title' => 'Fonts',
            'kicker' => 'Fourteen cores, then embed',
            'lead' => 'The fourteen PDF core fonts need no embedding. Anything else is a TrueType subset or a whole OpenType/CFF, built offline by tools/makefont and registered on a FontRepository.',
            'body' => [
                'This example stays on Helvetica so the file stays small at a hundred pages. The showcase and custom-font examples switch the repository; the layout engine does not care. Metrics come from the same JSON the writer uses to embed, so a width measured in the line breaker is the width that is painted.',
                'UTF-8 comes in and is transcoded per font. Windows-1252 is the default encoding for core fonts; anything else needs ext-iconv and a matching .json. A missing glyph is not a crash — it is a “.” or a skip, matching FPDF.',
                'CFF subsetting is still on the roadmap. Until then an OpenType font with PostScript outlines embeds whole, which is why the long examples prefer a core font or a TrueType cut.',
            ],
            'notes' => [
                'FontFace carries named and numeric weights, plus italic.',
                'ToUnicode CMaps are emitted for embedded fonts so copy-paste works.',
                'Core font metrics are the AFM-derived tables FPDF shipped.',
                'A FontRepository is shared across the measurer and the writer.',
            ],
            'terms' => [
                'core font' => 'One of the fourteen built into every PDF viewer.',
                'subset' => 'Only the glyphs this document used, for TTF.',
                'FontRepository' => 'The registry the renderer hands the measurer.',
            ],
        ],
        [
            'id' => 'draw',
            'title' => 'Drawing',
            'kicker' => 'Paths, charts, images',
            'lead' => 'Vector drawing is a Path of commands in a declared box. Charts are a thin layer over that path API. Images decode JPEG, PNG (with a soft mask), GIF and WebP from a path, a URL or a data URI.',
            'body' => [
                'Coordinates on a path are top-down, relative to the path’s own box — the same convention as every other node. The Y-flip is still only in PageGeometry. A gradient is an axial or radial shading; a clip is a path that is not painted, applied to a group of blocks.',
                'A chart never splits. Bar, line, pie and sparkline take a series of numbers, a nice-number axis, optional categories and a legend. Series colours left null are filled from a palette by position, so output stays deterministic without picking a palette by hand.',
                'This chapter’s figure is a bar chart of the eight chapters’ paragraph counts. It is not data about the library; it is a box the paginator has to place, the same as the paragraphs around it.',
            ],
            'notes' => [
                'Path factories inset by half the stroke so ink stays in the box.',
                'Fit, BoxAlign and ShrinkMode belong to absolute placement, not flow.',
                'GIF and WebP need ext-gd; JPEG and PNG do not.',
                'An image from http(s) is fetched once and cached for the render.',
            ],
            'terms' => [
                'Path' => 'Commands plus a Paint, occupying a fixed box.',
                'Chart' => 'Bar, line, pie or sparkline over Path.',
                'ImageBlock' => 'A raster in flow, sized by width, height or both.',
            ],
        ],
        [
            'id' => 'write',
            'title' => 'Serialise',
            'kicker' => 'Bytes, then stop',
            'lead' => 'The writer is the FPDF 1.9 port: objects, xref, trailer, stream compression. Comments that cite fpdf.php:NNN refer to that release, not a file in this repo. Refactor structure, not output.',
            'body' => [
                'Determinism is a feature the golden tests depend on. A FixedClock, compress: false and a fixed producer string make the same tree emit the same bytes. Hash ordering, timestamps and spl_* ids in output are defects.',
                'This example is not a golden. It is too large to commit, and example PDFs are gitignored. CI still renders it and runs qpdf --check, which is the point: a hundred-page file has to be a valid PDF, not merely a long one.',
                'When you change the layout engine, this file is a cheap way to notice that pagination still terminates, that headers still see the real N, and that a table header still repeats on the last sheet as on the first.',
            ],
            'notes' => [
                'Document::save() is PdfOutput::save() over toString().',
                'The producer string is an Info dictionary entry, not a watermark.',
                'qpdf --check is the structural job in CI, next to pdftotext.',
                'Golden files live in tests/golden and are the byte-stable suite.',
            ],
            'terms' => [
                'xref' => 'The offset table the trailer points at.',
                'FixedClock' => 'A clock that does not read the wall.',
                'Golden::assert()' => 'Byte compare against tests/golden.',
            ],
        ],
    ];
}

/**
 * @param array{id: string, title: string, kicker: string, lead: string, body: list<string>, notes: list<string>, terms: array<string, string>} $chapter
 */
function long_render_chapter(PageBuilder $p, array $chapter, int $index, Color $accent): void
{
    $p->anchor($chapter['id']);
    $p->heading(1, sprintf('%d.  %s', $index + 1, $chapter['title']));
    $p->paragraph($chapter['kicker'], new StylePatch(
        fontSizePt: 12.0,
        color: Color::gray(90),
        align: TextAlign::Left,
        italic: true,
        spaceAfterPt: 10.0,
    ));
    $p->paragraph($chapter['lead']);
    foreach ($chapter['body'] as $paragraph) {
        $p->paragraph($paragraph);
    }

    if ($index === 6) {
        $p->heading(2, 'Paragraphs per chapter');
        $values = [];
        $labels = [];
        foreach (long_chapters() as $item) {
            $labels[] = (string) $item['title'][0];
            $values[] = 1 + count($item['body']);
        }
        $p->chart(Chart::bar(
            [Series::of('Paragraphs', $values, $accent)],
            $labels,
            160.0,
            42.0,
            Unit::Mm,
            LegendPosition::Bottom,
        ));
    }

    $p->component(new Callout(
        $chapter['kicker'] . '. ' . $chapter['lead'],
        title: 'In short',
    ));

    $p->heading(2, 'Notes');
    $p->bulletList($chapter['notes']);

    $p->heading(2, 'Terms');
    $p->component(new DefinitionList($chapter['terms']));

    $p->heading(2, 'A page of the chapter, counted');
    $p->dataTable(
        DataTable::of([
            ['kind' => 'Paragraphs', 'count' => 1 + count($chapter['body'])],
            ['kind' => 'Notes', 'count' => count($chapter['notes'])],
            ['kind' => 'Terms', 'count' => count($chapter['terms'])],
            ['kind' => 'Charts', 'count' => $index === 6 ? 1 : 0],
        ])
            ->column('kind', 'Kind')
            ->column('count', 'Count', TextAlign::Right, ColumnWidth::fixed(72.0))
            ->headerBackground(Color::rgb(230, 236, 245))
            ->borderColor(Color::gray(205)),
    );

    $p->component(new Card(
        [
            new Paragraph(
                InlineSequence::of('Chapter ')
                    ->withBold($chapter['title'])
                    ->withRun(' is still just blocks in a tree. The next sheet is the same story, or, after the last chapter, the ledger.'),
                new StylePatch(spaceAfterPt: 0.0),
            ),
        ],
        title: 'Keep going',
        background: Color::rgb(249, 250, 252),
    ));
}

/**
 * @param list<array{id: string, title: string}> $chapters
 * @return list<array{id: string, chapter: string, node: string, width: float, height: float, split: string}>
 */
function long_ledger(int $rows, array $chapters): array
{
    $nodes = ['Paragraph', 'Heading', 'Table', 'Image', 'Chart', 'Container', 'List', 'Rule'];
    $perChapter = intdiv($rows, count($chapters));
    $out = [];
    $n = 0;
    foreach ($chapters as $chapterIndex => $chapter) {
        $count = $chapterIndex === count($chapters) - 1
            ? $rows - $n
            : $perChapter;
        for ($i = 0; $i < $count; $i++) {
            $n++;
            $out[] = [
                'id' => sprintf('B-%04d', $n),
                'chapter' => $chapter['title'],
                'node' => $nodes[($n + $chapterIndex) % count($nodes)],
                'width' => 120.0 + (float) (($n * 17) % 400),
                'height' => 8.0 + (float) (($n * 13) % 96),
                'split' => $n % 7 === 0 ? 'yes' : 'no',
            ];
        }
    }

    return $out;
}
