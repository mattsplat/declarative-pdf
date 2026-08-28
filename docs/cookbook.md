# Cookbook

Task-oriented recipes. Every snippet assumes `use Pdf\Document;` and the
relevant `Pdf\…` classes are imported.

## Headers and footers with page numbers

The closure receives a `PageContext` and returns block content. The real total
page count is known before any band renders — no `{nb}` placeholder.

```php
use Pdf\Layout\PageContext;
use Pdf\Node\Paragraph;
use Pdf\Style\StylePatch;
use Pdf\Style\TextAlign;

$p->header(fn (PageContext $c) => new Paragraph('Quarterly Report',
    new StylePatch(fontSizePt: 9, spaceAfterPt: 0)));

$p->footer(fn (PageContext $c) => new Paragraph(
    "Page {$c->pageNumber} of {$c->pageCount}",
    new StylePatch(fontSizePt: 9, align: TextAlign::Center, spaceAfterPt: 0)));
```

The band's measured height is reserved out of the content area automatically.

## Multi-page flow and page breaks

Content paginates on its own. Force a break with `pageBreak()`; keep a block
whole with `keepTogether`; keep a heading with the block after it (the default
for headings) with `keepWithNext`.

```php
$p->heading(2, 'Chapter 2')          // keepWithNext is on by default
  ->paragraph($longText)             // wraps + splits across pages
  ->pageBreak()
  ->heading(2, 'Appendix');

$p->paragraph($mustNotSplit, new \Pdf\Style\StylePatch(keepTogether: true));
```

Orphan/widow control lives on the style (`orphans` / `widows`, default 2).

## Tables with automatic column sizing

```php
use Pdf\Node\{Table, TableRow, TableCell};
use Pdf\Style\ColumnWidth;
use Pdf\Style\StylePatch;
use Pdf\Style\TextAlign;

$p->table([
    new TableRow(['Country', 'Capital', 'Area']),
    new TableRow(['Austria', 'Vienna', new TableCell('83,859',
        patch: new StylePatch(align: TextAlign::Right))]),
    // ...
], headerRows: 1);
```

Control widths explicitly:

```php
new Table($rows, [
    ColumnWidth::auto(minPt: 60),      // sized to content, at least 60pt
    ColumnWidth::fraction(2),          // twice the share of leftover space
    ColumnWidth::fixed(90),            // exactly 90pt
], headerRows: 1, repeatHeader: true, headerBackground: \Pdf\Color\Color::gray(235));
```

Long tables split at row boundaries and repeat their header rows on every page.

## Lists

```php
use Pdf\Node\{ListItem, Paragraph};

$p->bulletList(['First', 'Second', 'Third']);
$p->orderedList(['Step one', 'Step two'], start: 1);

// Rich items: a list of block nodes
$p->bulletList([
    new ListItem([new Paragraph('A heading-ish line', new \Pdf\Style\StylePatch(bold: true)),
                  new Paragraph('and its detail.')]),
]);
```

## Multi-column text

```php
use Pdf\Node\Paragraph;

$p->columns([
    new Paragraph($article),
], count: 2, gutterPt: 18);
```

Balanced when it fits a page; fills column-by-column then page-by-page when it
overflows.

## Inline formatting

```php
use Pdf\Text\InlineSequence;

$p->paragraph(
    InlineSequence::of('Plain, ')
        ->withBold('bold')->withRun(', ')
        ->withItalic('italic')->withRun(', ')
        ->withUnderline('underline')->withRun(', H')
        ->withSubscript('2')->withRun('O, E = mc')
        ->withSuperscript('2')->withRun('. ')
        ->withLink('a link', 'https://example.com')
        ->withRun('.')
        ->withBreak()
        ->withRun('Second line, same paragraph.'),
);
```

Or from a small subset of HTML:

```php
$p->html('Use <b>bold</b>, <i>italic</i>, x<sup>2</sup>, '
    . '<a href="https://example.com">links</a> and <br>breaks.');
```

## Internal links (table of contents)

```php
use Pdf\Text\InlineSequence;

// somewhere near the top
$p->paragraph(InlineSequence::of('Jump to ')->withLink('Methods', '#methods'));

// later, right before the target
$p->anchor('methods')->heading(2, 'Methods');
```

The anchor's position is resolved after layout and travels with its heading
across page breaks.

## Images

```php
$p->image('logo.png', width: 40);                 // block image, flows in the stack
$p->image('photo.jpg');                            // natural size at 96 dpi

// inline, on the text baseline
$p->paragraph(\Pdf\Text\InlineSequence::of('See the icon ')
    ->withImage('icon.png', width: 4)
    ->withRun(' here.'));
```

PNG transparency becomes a soft mask; WebP transparency is preserved.

Any image source — `image()`, `placeImage()`, `withImage()` — also accepts an
`http(s)://` URL or a `data:` URI (no Composer dependency; the fetch uses the
streams layer, falling back to `ext-curl` if `allow_url_fopen` is off). Pass a
URL only when you trust it, and note the fetch runs during layout:

```php
$p->placeImage(0, 0, 60, 20, 'https://cdn.example.com/nameplate.png');

// bytes you already hold (fetched yourself, generated, pulled from a blob store)
$p->placeImageData(0, 0, 60, 20, $pngBytes);
```

## Large-format sheets laid out in areas

Work in inches, position content in explicit rectangles.

```php
use Pdf\Geometry\{BoxAlign, Fit, PageSize, Unit};
use Pdf\Node\Paragraph;
use Pdf\Style\Border;

$p->size(PageSize::arch('d'))->landscape()->units(Unit::In)->margin(0);

$p->frame(0.5, 0.5, 35, 23, Border::uniform(1.5));          // sheet border
$p->frame(1, 1, 26, 21, Border::uniform(0.5));              // drawing viewport
$p->placePdf(1, 1, 26, 21, 'floor-plan.pdf', page: 1, fit: Fit::Contain);

$p->place(28, 1, 6, 10, [                                    // notes column
    new Paragraph('GENERAL NOTES', new \Pdf\Style\StylePatch(bold: true)),
    new Paragraph('1. Verify all dimensions on site.'),
], BoxAlign::TopLeft);
```

Block content placed in an area shrinks uniformly to fit the rectangle's
height. Pass `shrink: ShrinkMode::FontSize` for a text legend or note instead —
it lowers the effective font size (hard-coded `fontSizePt` included) so the
lines re-wrap rather than squash, then draws at 1:1:

```php
use Pdf\Geometry\ShrinkMode;

$p->place(28, 1, 6, 10, [
    new Paragraph('GENERAL NOTES', new \Pdf\Style\StylePatch(bold: true, fontSizePt: 11)),
    new Paragraph('1. Verify all dimensions on site before fabrication.'),
    new Paragraph('2. This drawing is diagrammatic and not to scale.'),
], BoxAlign::TopLeft, ShrinkMode::FontSize);
```

`placeImage()` takes a `Fit` mode; `placePdf()` imports one page of an
external PDF as a **vector Form XObject** (stays crisp at any zoom).

## Measuring text and blocks for absolute layout

`textWidth()` and `measureBlocks()` let a `page()` closure position content
against real metrics — right-align a string, or size a `place()` rectangle to
its content. Both return the page's `units()`; the closure runs at render time,
so the fonts (including any registered with `using()`) are known.

```php
use Pdf\Geometry\Unit;
use Pdf\Node\Paragraph;
use Pdf\Style\StylePatch;

Document::create()->page(function ($p) {
    $p->units(Unit::In);

    $title = 'DETAIL';
    $p->place(1, 1, 6, 0.4, [new Paragraph($title, new StylePatch(bold: true, fontSizePt: 14))]);

    // Start the next label just past the title.
    $x = 1 + $p->textWidth($title, new StylePatch(bold: true, fontSizePt: 14)) + 0.1;
    $p->place($x, 1, 3, 0.4, [new Paragraph('(revised)', new StylePatch(fontSizePt: 9))]);

    // Size a note box to exactly the height its copy needs.
    $notes = [new Paragraph('1. Verify all dimensions on site.')];
    $p->place(1, 2, 4, $p->measureBlocks($notes, 4), $notes);
})->save('sheet.pdf');
```

```php
use Pdf\Font\FontStyle;
use Pdf\Text\TextMeasurer;

$pt = TextMeasurer::withBundledFonts()->widthOf('DETAIL', 'Helvetica', FontStyle::Bold, 14.0);
```

`TextMeasurer` does the width half in points, with no builder.

## Importing / reading an external PDF

```php
use Pdf\Import\PdfImportDocument;

$doc = PdfImportDocument::fromFile('report.pdf');
$doc->pageCount();                    // int
$page = $doc->page(2);               // ImportedPage: bounding box, rotation, content
```

Trusted, unencrypted sources only. Source annotations, links and form fields
are dropped — only the visual content is imported. For a full document merge
that preserves outlines and links, shell out to `qpdf`.

## Custom (embedded) fonts

Build a definition with the offline tool, then register and use it:

```
php tools/makefont/makefont.php Inter-Regular.ttf cp1252
# -> Inter-Regular.json (+ Inter-Regular.z)
```

```php
use Pdf\Font\FontRepository;
use Pdf\Font\FontFace;
use Pdf\Render\DocumentRenderer;
use Pdf\Style\StylePatch;

$fonts = FontRepository::withBundledFonts();
$fonts->register('Inter', FontFace::regular(), __DIR__ . '/fonts/Inter-Regular.json');

Document::create()
    ->using(new DocumentRenderer($fonts))
    ->page(fn ($p) => $p->paragraph('In Inter.',
        new StylePatch(fontFamily: 'Inter')))
    ->save('out.pdf');
```

The subsetted font program is embedded with `/FontFile2`, a `/FontDescriptor`
and a ToUnicode CMap so the text stays copy-pasteable.

`.otf` files work the same way. Ones with TrueType outlines take the path
above; ones with PostScript (CFF) outlines produce a `Type1` definition whose
program is the `CFF ` table, embedded as `/FontFile3` `/Subtype /Type1C`:

```
php tools/makefont/makefont.php IBMPlexSans-Regular.otf cp1252
# -> IBMPlexSans-Regular.json (+ IBMPlexSans-Regular.cff.z)
```

CFF programs are **not** subsetted — the whole font (40–80 KB per cut) is
embedded.

## Named and numeric font weights

Register one definition per cut and select it with `weight` (100–900):

```php
use Pdf\Font\FontFace;

$fonts->register('Inter', new FontFace(300), __DIR__ . '/fonts/Inter-Light.json');
$fonts->register('Inter', new FontFace(400), __DIR__ . '/fonts/Inter-Regular.json');
$fonts->register('Inter', new FontFace(600), __DIR__ . '/fonts/Inter-SemiBold.json');
$fonts->register('Inter', new FontFace(600, italic: true), __DIR__ . '/fonts/Inter-SemiBoldItalic.json');

new StylePatch(fontFamily: 'Inter', weight: 600);
new StylePatch(fontFamily: 'Inter', bold: true);   // ≡ weight: 700 -> snaps to the 600 cut
```

An unregistered weight falls back to the nearest one in the same slope, then to
the nearest in the other slope. A single-cut `.otf`/CFF family follows the same
ladder.

The core families (Helvetica, Times, Courier, …) are the exception: they ship a
file for every cut, so registering one cut of a core family overrides that cut
alone — `register('Helvetica', new FontFace(400), …)` re-skins body text and
still leaves headings on bundled Helvetica-Bold. They only carry 400 and 700 per
slope, so `weight: 600` on Helvetica draws Helvetica-Bold.

## A house style

```php
use Pdf\Color\Color;
use Pdf\Style\{Stylesheet, StylePatch};

$sheet = (new Stylesheet())
    ->heading(1, new StylePatch(color: Color::rgb(20, 50, 110), fontSizePt: 26))
    ->heading(2, new StylePatch(color: Color::rgb(20, 50, 110)))
    ->paragraph(new StylePatch(lineHeight: 1.45, spaceAfterPt: 8));

Document::create()->stylesheet($sheet)->page(...)->save('out.pdf');
```

Rules apply between the built-in defaults and each node's own `StylePatch`.
