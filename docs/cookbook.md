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

For a plain page number, `pageNumbers()` is the shorthand — `{n}` is the
current page, `{N}` the total:

```php
$p->pageNumbers();                                  // "Page 1 of 12", centred footer
$p->pageNumbers('{n} / {N}', align: TextAlign::Right, inHeader: true);

Document::create()->pageNumbers()->page(...)->...;  // on every page
```

## Watermarks

`watermark()` stamps a word across every sheet — rotated, centred on the whole
page, translucent, over the content:

```php
$p->watermark('DRAFT');

use Pdf\Node\Watermark;
use Pdf\Color\Color;

$p->watermark(new Watermark(
    'CONFIDENTIAL',
    color: Color::rgb(180, 30, 30),
    opacity: 0.10,        // < 1 emits an /ExtGState
    angleDeg: 45,
    overlay: false,       // behind the content instead of over it
));

Document::create()->watermark('DRAFT')->page(...)->...;  // whole document
```

The font size auto-fits the page diagonal unless you pass `fontSizePt`. A page
may override the document watermark with its own `watermark()`.

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

## Drawing shapes

`Path` is a block node, so linework stacks in normal flow or lands in a
`place()` rectangle like anything else. Coordinates are relative to the path's
own box, top-left origin — the same convention as the rest of the library.

```php
use Pdf\Color\Color;
use Pdf\Node\Path;
use Pdf\Style\{FillRule, LineJoin, Paint};

$p->path(Path::rectangle(60, 14, Paint::filled(Color::fromHex('#2f6fbf'))));
$p->path(Path::ellipse(60, 18, new Paint(
    fill: Color::white(),
    stroke: Color::fromHex('#2f6fbf'),
    strokeWidthPt: 1.5,
)));
$p->path(Path::line(0, 0, 170, 0, Paint::stroked(Color::gray(140), 1.0)));
```

Sizes are in the page's units by default; pass `Unit::Pt` (or any other) to a
factory to change that. A `Paint` with neither `fill` nor `stroke` draws a
hairline black outline.

For an arbitrary figure, build the command list yourself. A `moveTo` starts a
new subpath, so one list can describe several — with `FillRule::EvenOdd` that
is how you punch a hole:

```php
use Pdf\Geometry\PathCommand;

$p->path(Path::of([
    PathCommand::moveTo(0, 0),
    PathCommand::lineTo(40, 0),
    PathCommand::curveTo(40, 22, 22, 40, 0, 40),
    PathCommand::close(),
], width: 40, height: 40, paint: Paint::filled(Color::gray(60), FillRule::EvenOdd)));
```

### A bar chart from rectangles

There is no chart layer yet; one `place()` per bar is all it takes.

```php
$values = ['Q1' => 32, 'Q2' => 58, 'Q3' => 41, 'Q4' => 76];
$top = 165.0;
$height = 55.0;

foreach (array_values($values) as $i => $value) {
    $bar = $height * $value / 80;
    $p->place(22 + $i * 40, $top + $height - $bar, 26, $bar, [
        Path::rectangle(26, $bar, Paint::filled(Color::fromHex('#2f6fbf'))),
    ], shrink: \Pdf\Geometry\ShrinkMode::None);
}

$p->place(22, $top + $height, 152, 1, [
    Path::line(0, 0, 152, 0, Paint::stroked(Color::gray(60), 1.0)),
], shrink: \Pdf\Geometry\ShrinkMode::None);
```

`ShrinkMode::None` keeps the shape at its stated size; the default `Scale`
would shrink content taller than its area. `examples/shapes.php` is the whole
thing.

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

To **concatenate** PDFs, stamp every source page onto a new page of the same
size (`examples/merge.php` is the runnable version):

```php
use Pdf\Geometry\{BoxAlign, Fit, Orientation, PageSize, Unit};
use Pdf\Import\PdfImportDocument;

$merged = Document::create();

foreach (['cover.pdf', 'body.pdf', 'appendix.pdf'] as $path) {
    $source = PdfImportDocument::fromFile($path);
    for ($n = 1; $n <= $source->pageCount(); $n++) {
        $page = $source->page($n);
        [$w, $h] = [$page->widthPt(), $page->heightPt()];
        $merged->page(fn ($p) => $p
            ->size(PageSize::fromUnits($w, $h, Unit::Pt))
            ->orientation($w >= $h ? Orientation::Landscape : Orientation::Portrait)
            ->units(Unit::Pt)->margin(0)
            ->placePdf(0, 0, $w, $h, $path, $n, Fit::Contain, BoxAlign::Center));
    }
}
$merged->save('merged.pdf');
```

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
    ->paragraph(new StylePatch(lineHeight: 1.45, spaceAfterPt: 8))
    ->class('lead', new StylePatch(fontSizePt: 14, color: Color::gray(60)));

Document::create()->stylesheet($sheet)->page(...)->save('out.pdf');
```

Rules apply between the built-in defaults and each node's own `StylePatch`.

A node opts into a named class rule with `StylePatch(class: 'lead')` (a
space-separated list is allowed: `class: 'lead callout'`):

```php
$page->paragraph('A short standfirst.', new StylePatch(class: 'lead'));
```

Class rules are consulted after the node-type rule — a class beats the type,
a class listed later beats one listed earlier — and the node's own patch still
wins over all of them. `class` is **block-level**: it does nothing on an inline
run. `->class('lead', …)` and `->class('table', …)` share no namespace with the
node-type rules, so a class name may safely match a node type.

## Reusable components

Subclass `Component`: take your parameters in the constructor, return the tree
from `body()`. A non-empty `patch()` frames the body like an implicit
`Container` (padding, border, background, inherited style).

```php
use Pdf\Node\{Component, BlockNode, Paragraph, Rule};
use Pdf\Style\{Border, StylePatch};
use Pdf\Color\Color;
use Pdf\Geometry\Edges;

final readonly class Callout extends Component
{
    public function __construct(private string $text) {}

    public function body(): BlockNode
    {
        return new Paragraph($this->text, new StylePatch(fontSizePt: 10));
    }

    public function patch(): StylePatch
    {
        return new StylePatch(
            paddingPt: Edges::all(8),
            background: Color::rgb(255, 245, 150),
            border: Border::uniform(0.5, Color::gray(180)),
            keepTogether: true,
        );
    }
}
```

A component takes a **slot** by accepting child blocks — `body()` can `yield`:

```php
final readonly class Card extends Component
{
    /** @param iterable<BlockNode> $content */
    public function __construct(private string $title, private iterable $content) {}

    public function body(): iterable
    {
        yield new Paragraph($this->title, new StylePatch(bold: true, fontSizePt: 13, spaceAfterPt: 4));
        yield new Rule(0.5, Color::gray(200));
        yield from $this->content;
    }

    public function patch(): StylePatch
    {
        return new StylePatch(paddingPt: Edges::all(12), border: Border::uniform(0.75));
    }
}
```

Use one anywhere a block goes — page flow, a `Container`, a `TableCell`, a
`place()` area:

```php
$p->component(new Callout('Payment due within 30 days.'));
$p->component(new Card('Line items', [
    new Paragraph('Design — $1,200'),
    new Paragraph('Build — $4,800'),
]));
// nest, and re-style from the outside with a Container:
$p->container([new Callout('Thanks!')], new StylePatch(spaceBeforePt: 20));
```

`body()` is called more than once per render (intrinsic sizing, each pagination
pass) — keep it pure. A component whose `body()` reaches itself raises a
`LayoutException` instead of recursing forever.
