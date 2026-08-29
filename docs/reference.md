# API reference

Namespace root is `Pdf\`. Everything is strict-typed; value objects are
`readonly`. Points are the internal unit; API inputs in other units are
converted at the boundary.

- [Entry point](#entry-point)
- [DocumentBuilder](#documentbuilder)
- [PageBuilder](#pagebuilder)
- [Block nodes](#block-nodes)
- [Inline content](#inline-content)
- [Style](#style)
- [Geometry](#geometry)
- [Rendering & output](#rendering--output)
- [Fonts](#fonts)
- [PDF import](#pdf-import)
- [Exceptions](#exceptions)

---

## Entry point

```php
Pdf\Document::create() : Pdf\Builder\DocumentBuilder
Pdf\Document::render(Pdf\Node\Document $tree, ?Pdf\Render\DocumentRenderer $r = null) : string
```

`create()` starts the fluent builder. `render()` renders an already-built
immutable tree.

---

## DocumentBuilder

| method | notes |
|---|---|
| `meta(callable(MetaBuilder))` | `title` / `author` / `subject` / `keywords` / `creator` — each `MetaBuilder` method returns `$this` |
| `page(callable(PageBuilder))` | append a logical page; the closure runs at `build()` / `toString()` time, once fonts are known, so it can call the measurement helpers below |
| `addPage(Pdf\Node\Page)` | append a pre-built page |
| `baseStyle(Pdf\Style\Style)` | document-wide defaults (default `Style::default()`) |
| `stylesheet(Pdf\Style\Stylesheet)` | per-node-type rules |
| `pageNumbers(string $format = 'Page {n} of {N}', TextAlign = Center, float $fontSizePt = 9, ?Color = null, bool $inHeader = false)` | page numbers on every `page()` |
| `watermark(string\|Pdf\Node\Watermark)` | stamp on every `page()`; a page may override |
| `bookmark(string $title, string $anchor, int $level = 0)` | add an outline entry pointing at an existing `anchor()`; `$level` 0 is top-level, deeper items nest under the nearest preceding lower level in call order. Unresolved anchor → `PdfException` |
| `using(Pdf\Render\DocumentRenderer)` | custom renderer (fonts, clock, compression, producer) |
| `build() : Pdf\Node\Document` | the immutable tree |
| `toString() : string` | render to PDF bytes |
| `output() : Pdf\Output\PdfOutput` | render, wrapped for sending |
| `save(string $path) : void` | render and write to a file |

---

## PageBuilder

### Geometry

| method | |
|---|---|
| `size(PageSize)` | default `PageSize::a4()` |
| `orientation(Orientation)` · `landscape()` | |
| `margin(float $value, Unit = Mm)` | uniform margin |
| `margins(Edges $marginsPt)` | per-edge, in points |
| `units(Unit)` | the unit used by `place*()` / `frame()` coordinates (default `Mm`) |

### Header / footer / page numbers / watermark

```php
header(Closure(PageContext): (BlockNode | iterable<BlockNode>))
footer(Closure(PageContext): (BlockNode | iterable<BlockNode>))
pageNumbers(string $format = 'Page {n} of {N}', TextAlign = Center,
            float $fontSizePt = 9, ?Color = null, bool $inHeader = false) : self
watermark(string | Pdf\Node\Watermark) : self
```

`Pdf\Layout\PageContext` — `int $pageNumber`, `int $pageCount`,
`float $contentWidthPt`. In a `pageNumbers()` format, `{n}` is the current page
and `{N}` the total.

`Pdf\Node\Watermark(string $text, Color $color = gray(120), float $opacity = 0.12,
float $angleDeg = 45, ?float $fontSizePt = null, string $fontFamily = 'Helvetica',
FontFace $fontFace = new FontFace(700), bool $overlay = true)` — drawn once per
sheet, centred on the whole page and rotated; `opacity` below 1 emits an
`/ExtGState`, `fontSizePt` null auto-fits the page diagonal.

### Content

| method | node produced |
|---|---|
| `heading(int $level 1-6, string\|InlineSequence, StylePatch = new)` | `Heading` |
| `paragraph(string\|InlineSequence, StylePatch = new)` | `Paragraph` |
| `html(string, StylePatch = new)` | `Paragraph` from inline HTML |
| `spacer(float $height, Unit = Mm)` | `Spacer` |
| `rule(float $thicknessPt = 0.5, ?Color = null)` | `Rule` |
| `pageBreak()` | `PageBreak` |
| `anchor(string $name)` | `Anchor` (internal-link target) |
| `image(string $source, ?float $width = null, ?float $height = null, Unit = Mm, TextAlign = Left)` | `ImageBlock` — `source` is a path, `http(s)://` URL, or `data:` URI |
| `container(iterable<BlockNode>, StylePatch = new)` | `Container` — the patch adds padding/border/background *and* cascades its inheriting style (font, colour, alignment, …) to descendants |
| `bulletList(iterable<ListItem\|string>, StylePatch = new)` | `BulletList` |
| `orderedList(iterable<ListItem\|string>, int $start = 1, StylePatch = new)` | `OrderedList` |
| `columns(iterable<BlockNode>, int $count = 2, float $gutterPt = 14.0)` | `Columns` |
| `table(iterable<TableRow>, ?ColumnWidth[] $columns = null, int $headerRows = 0, ?float $totalWidthPt = null)` | `Table` |
| `add(BlockNode)` | any node directly |

### Measurement

Both return values in the page's `units()`. Only available when the page is
built through `DocumentBuilder::page()` (a directly-constructed `PageBuilder`
has no renderer and these throw `LayoutException`).

| method | |
|---|---|
| `textWidth(string $text, StylePatch = new)` | advance width of one unbroken line (no wrapping, no `\n`); the patch resolves against the document base style, so the result matches the line breaker |
| `measureBlocks(iterable<BlockNode> $blocks, float $width)` | natural stacked height of `$blocks` flowed at `$width` — size a `place()` rectangle to its content |

For measuring outside the builder, `Pdf\Text\TextMeasurer` does the same in
points — see [Inline content](#inline-content).

### Absolute areas

Coordinates in the page's `units()`. Placements render on the **first physical
sheet** of the logical page, on top of the flow content.

| method | |
|---|---|
| `place(float $x, $y, $w, $h, iterable<BlockNode>, BoxAlign = TopLeft, ShrinkMode = Scale)` | block content; flows at width `w`, fitted to height `h` per `ShrinkMode` |
| `placeImage(float $x, $y, $w, $h, string $source, Fit = Contain, BoxAlign = Center)` | a raster image; `source` is a path, an `http(s)://` URL, or a `data:` URI |
| `placeImageData(float $x, $y, $w, $h, string $bytes, Fit = Contain, BoxAlign = Center)` | a raster image already in memory (carried inline as a `data:` URI — use `placeImage` with a path for large images) |
| `placePdf(float $x, $y, $w, $h, string $path, int $page = 1, Fit = Contain, BoxAlign = Center)` | one page of an external PDF as a vector Form XObject |
| `frame(float $x, $y, $w, $h, Border = new, ?Color $background = null)` | a bordered / filled rectangle |

---

## Block nodes

`Pdf\Node\*`, all implementing `BlockNode`. Constructors take a trailing
`StylePatch` unless noted.

| node | constructor |
|---|---|
| `Heading` | `(int $level, string\|InlineSequence $content, StylePatch)` |
| `Paragraph` | `(string\|InlineSequence $content, StylePatch)` |
| `Spacer` | `(float $heightPt)` — or `Spacer::of(float, Unit)` |
| `Rule` | `(float $thicknessPt = 0.5, ?Color $color = null, StylePatch)` |
| `PageBreak` | `()` |
| `Anchor` | `(string $name)` |
| `Container` | `(iterable<BlockNode> $children, StylePatch)` |
| `BulletList` | `(iterable<ListItem\|string> $items, string $marker = "•", float $gutterPt = 18, float $itemSpacingPt = 3, StylePatch)` |
| `OrderedList` | `(iterable<ListItem\|string> $items, int $start = 1, string $suffix = ".", float $gutterPt = 22, float $itemSpacingPt = 3, StylePatch)` |
| `ListItem` | `(string\|InlineSequence\|iterable<BlockNode> $content, StylePatch)` |
| `Columns` | `(iterable<BlockNode> $children, int $count = 2, float $gutterPt = 14, StylePatch)` |
| `ImageBlock` | `(string $path, ?float $widthPt = null, ?float $heightPt = null, TextAlign = Left, float $dpi = 96, StylePatch)` — or `ImageBlock::of(string, ?float $w, ?float $h, Unit = Mm, TextAlign = Left)`. `$path` may be a filesystem path, an `http(s)://` URL, or a `data:` URI |
| `Table` | `(iterable<TableRow> $rows, ?ColumnWidth[] $columns = null, ?float $totalWidthPt = null, int $headerRows = 0, bool $repeatHeader = true, float $borderWidthPt = 0.5, Color $borderColor = black, Edges $cellPaddingPt = 3/4/3/4, ?Color $headerBackground = null, StylePatch)` |
| `TableRow` | `(iterable<TableCell\|string\|InlineSequence> $cells)` |
| `TableCell` | `(string\|InlineSequence\|iterable<BlockNode> $content, int $colspan = 1, VerticalAlign = Top, StylePatch, ?Color $background = null)` |
| `Component` | `abstract` — subclass it; see below |

### `Pdf\Node\Component`

A reusable block. Subclass, take parameters in the constructor, return the tree
from `body()`:

```php
abstract public function body() : BlockNode | iterable<BlockNode>   // the expansion
public function patch() : StylePatch                                // optional: wraps body() like a Container
```

A component composes anywhere a `BlockNode` does and expands during the measure
pass; a non-empty `patch()` frames its `body()` (padding / border / background /
inherited style). `body()` must be pure — it is called more than once per
render. `$page->component($x)` is sugar for `$page->add($x)`. See the
[cookbook recipe](cookbook.md#reusable-components).

### `Pdf\Node\Document`, `Page`, `PageMaster`, `Meta`

```php
new Document(iterable<Page> $pages, Meta $meta = new, ?Style $baseStyle = null, ?Stylesheet $stylesheet = null, iterable<Bookmark> $bookmarks = [])
new Bookmark(string $title, string $anchor, int $level = 0)   // outline entry; $anchor names an Pdf\Node\Anchor
new Page(PageMaster $master = new, iterable<BlockNode> $children = [], StylePatch $patch = new, iterable<Placement> $placements = [])
new PageMaster(PageSize $size = a4, Orientation = Portrait, Edges $marginsPt = 28.35, ?Closure $header = null, ?Closure $footer = null)
  PageMaster::of(PageSize, Orientation = Portrait, float $margin = 10, Unit $marginUnit = Mm)
  $master->withHeader(Closure) : PageMaster    $master->withFooter(Closure) : PageMaster
new Meta(?string $title = null, ?author = null, ?subject = null, ?keywords = null, ?creator = null)
```

---

## Inline content

### `Pdf\Text\InlineSequence`

```php
InlineSequence::of(string) : self
InlineSequence::empty() : self
InlineSequence::fromRuns(TextRun[]) : self

->withRun(string, StylePatch = new) : self
->withBold(string) ->withItalic(string) ->withUnderline(string) ->withStrikethrough(string)
->withSuperscript(string) ->withSubscript(string)
->withLink(string $text, string $target, ?StylePatch = null) : self     // target: URI or "#anchor"; default style blue + underline
->withBreak() : self                                                    // hard line break
->withImage(string $path, ?float $width = null, ?float $height = null, Unit = Mm) : self
->plainText() : string
->isEmpty() : bool
```

A `\n` anywhere in a run's text is also a hard line break. All text input is
UTF-8 and is transcoded to the target font's encoding for measuring and output.

### `Pdf\Text\TextMeasurer`

```php
new TextMeasurer(Pdf\Font\FontRegistry $fonts)
TextMeasurer::withBundledFonts() : self
->width(string $text, Pdf\Style\Style $style) : float               // points
->widthOf(string $text, string $family, Pdf\Font\FontFace $face, float $sizePt) : float
```

Advance width of one unbroken run, in points. Transcodes to the resolved
font's encoding first, so the result agrees with the line breaker.
`PageBuilder::textWidth()` wraps this and converts to the page's units.

### `Pdf\Text\Html`

```php
Html::toInline(string $html) : InlineSequence
```

Recognised tags: `b`/`strong`, `i`/`em`, `u`, `s`/`strike`/`del`, `sup`, `sub`,
`a href="…"`, `br`. Tags nest; attributes on known tags are ignored; entities
are decoded; whitespace is collapsed. Any other `<…>` span is kept as literal
text. Inline only — no block tags. Also `$page->html('…')`.

---

## Style

### `Pdf\Style\StylePatch`

Every constructor argument is nullable and defaults to `null` ("inherit").

| field | type | meaning |
|---|---|---|
| `fontFamily` | `string` | e.g. `'Helvetica'`, `'Times'`, `'Courier'`, `'Symbol'`, a registered name |
| `fontStyle` | `FontStyle` | `Regular` / `Bold` / `Italic` / `BoldItalic` |
| `weight` | `int` | numeric cut, 100–900; wins over `bold` |
| `bold` / `italic` | `bool` | toggle without naming the full style (`bold: true` ≡ `weight: 700`) |
| `fontSizePt` | `float` | absolute size |
| `fontSizeScale` | `float` | multiply the inherited size (used by sub/superscript) |
| `color` | `Color` | text colour |
| `align` | `TextAlign` | `Left` / `Right` / `Center` / `Justify` |
| `lineHeight` | `float` | multiple of the font size |
| `spaceBeforePt` / `spaceAfterPt` | `float` | block margins (collapse between blocks) |
| `paddingPt` | `Edges` | inner padding (Container, table cells) |
| `border` | `Border` | box border |
| `background` | `Color` | box background |
| `underline` / `strikethrough` | `bool` | text decorations |
| `baselineShift` | `float` | fraction of the font size; positive raises |
| `keepWithNext` | `bool` | keep on the same page as the following block |
| `keepTogether` | `bool` | never split across a page |
| `orphans` / `widows` | `int` | min lines left behind / carried forward (default 2) |

Helpers: `StylePatch::superscript()`, `StylePatch::subscript()`,
`StylePatch::none()`. `$patch->applyTo(Style) : Style`.

### `Pdf\Style\Style`

`Style::default()` — Helvetica / Regular / 12 pt / black / left / line-height
1.15 / no spacing / no box properties. Headings additionally get Bold, a size
scale (h1 ×2, h2 ×1.5, h3 ×1.17, h4 ×1.0, h5 ×0.83, h6 ×0.75, off the resolved
base size) and `keepWithNext`.

### `Pdf\Style\Stylesheet`

```php
(new Stylesheet())
  ->heading(int $level, StylePatch)
  ->paragraph(StylePatch)
  ->set(string $selector, StylePatch)      // 'h1'..'h6', 'paragraph', 'list', 'table', 'container'
```

Applied by the resolver **between** the built-in defaults and the node's own
`StylePatch`.

### `Pdf\Style\ColumnWidth`

```php
ColumnWidth::auto(?float $minPt = null, ?float $maxPt = null)
ColumnWidth::fixed(float $widthPt)
ColumnWidth::fraction(float $weight = 1.0, ?float $minPt = null, ?float $maxPt = null)
```

Auto columns size to content between min/max intrinsic width; leftover space
goes to `fraction` columns by weight (else equally to `auto`). Column widths
always sum to `max(available, Σ minimum)`.

### `Pdf\Style\Border`

```php
Border::none()
Border::uniform(float $widthPt, Color $color = black)
new Border(Edges $widthPt = new, Color $color = black)
```

### `Pdf\Style\VerticalAlign` · `Pdf\Style\TextAlign`

`VerticalAlign` — `Top` / `Middle` / `Bottom` (table cells).
`TextAlign` — `Left` / `Right` / `Center` / `Justify`.

### `Pdf\Color\Color`

```php
Color::rgb(int $r, $g, $b)   Color::gray(int)   Color::black()   Color::white()
Color::fromHex(string)        // '#rrggbb' or '#rgb'
$color->equals(Color) : bool
```

---

## Geometry

### `Pdf\Geometry\Unit`

`Pt` / `Mm` / `Cm` / `In`. `$unit->toPoints(float)`, `$unit->fromPoints(float)`.

### `Pdf\Geometry\Orientation`

`Portrait` / `Landscape`.

### `Pdf\Geometry\PageSize`

```php
PageSize::a0() … PageSize::a5()
PageSize::letter()   PageSize::legal()   PageSize::tabloid()   // (alias: 'ledger')
PageSize::arch(string 'a'|'b'|'c'|'d'|'e'|'e1')                // architectural
PageSize::ansi(string 'a'|'b'|'c'|'d'|'e')                     // ANSI/ASME
PageSize::named(string)                                        // any of the above by name
PageSize::fromUnits(float $w, $h, Unit)
new PageSize(float $widthPt, $heightPt)
```

### `Pdf\Geometry\Edges`

```php
new Edges(float $top = 0, $right = 0, $bottom = 0, $left = 0)
Edges::all(float)   Edges::symmetric(float $vertical, $horizontal)   Edges::zero()
```

### `Pdf\Geometry\BoxAlign` · `Pdf\Geometry\Fit`

`BoxAlign` — `TopLeft` `TopCenter` `TopRight` `CenterLeft` `Center` `CenterRight`
`BottomLeft` `BottomCenter` `BottomRight`.

`Fit` — `Contain` (scale to fit, keep aspect) · `Cover` (scale to fill, clip) ·
`Stretch` (fill both axes) · `ActualSize` (no scaling, clip) · `FitWidth` ·
`FitHeight`.

### `Pdf\Geometry\ShrinkMode`

How `place()` fits block content taller than its area:

`Scale` (default) — scale the rendered content down geometrically; strokes thin
out and the column under-fills its width. `FontSize` — lower the effective font
size (binary search in `[0.5, 1.0]`) so lines re-wrap and re-flow, then draw at
1:1; hard-coded `StylePatch(fontSizePt: …)` sizes shrink too. Falls back to
`Scale` if the 0.5 floor still overflows. `None` — leave content at natural
size; taller content spills past the area.

---

## Rendering & output

### `Pdf\Render\DocumentRenderer`

```php
new DocumentRenderer(
    Pdf\Font\FontRepository $fontRepository,        // FontRepository::withBundledFonts()
    Pdf\Support\Clock $clock = new SystemClock(),
    bool $compress = true,
    string $producer = 'mattsplat/declarative-pdf',
)
DocumentRenderer::default() : self
$renderer->render(Pdf\Node\Document) : string
```

`Pdf\Support\Clock` implementations: `SystemClock`, `FixedClock::at(string $iso8601)`.

### `Pdf\Output\PdfOutput`

```php
new PdfOutput(string $bytes)
->toString() : string
->save(string $path) : void
->inline(string $name = 'doc.pdf') : void        // echoes; sets inline headers (non-CLI)
->download(string $name = 'doc.pdf') : void       // echoes; sets attachment headers
```

---

## Fonts

### `Pdf\Font\FontRepository`

```php
FontRepository::withBundledFonts() : self         // the standard-14 core fonts
new FontRepository(string $fontDirectory, FontLoader $loader = new)
->register(string $family, FontFace $face, string $definitionPath) : void
->resolve(string $family, FontFace $face) : FontDefinition
```

A `register()`ed font wins over the `arial → helvetica` alias and takes effect
even after the family was previously resolved. Build a definition file with
`php tools/makefont/makefont.php Font.ttf cp1252`.

`.ttf` and `.otf` are both accepted:

| Source | Definition | Embedded as |
|---|---|---|
| `.ttf` / `.otf` with TrueType outlines | `type: TrueType`, `subsetted: true` | `/FontFile2`, subsetted |
| `.otf` with PostScript (CFF) outlines | `type: Type1`, `cff: true`, `size1` | `/FontFile3` `/Subtype /Type1C`, whole font |

CFF fonts are limited to 256 glyphs (WinAnsi + `/Differences`) and are not
subsetted; CID-keyed CFF and CFF2 (variable) fonts are rejected by the tool.

A cut resolves in this order: the exact registered cut → the bundled file, for
a core family → the nearest registered cut (nearest weight in the same slope,
else nearest in the other slope; ties to the lighter). Registering one cut of a
core family therefore overrides that cut only and leaves the rest bundled. A
family with neither a core fallback nor any registered cut raises a
`FontException`.

The core families only carry 400 and 700 per slope, so `new FontFace(600)`
resolves to the bold file. Weights outside 1–1000 are rejected.

### `Pdf\Font\FontFace`

One cut of a family — a CSS/OpenType weight plus a slope.

```php
new FontFace(int $weight = 400, bool $italic = false)   // weight 1..1000
FontFace::regular() ::bold() ::italic() ::boldItalic()
FontFace::fromLegacy(FontStyle $style) : self
->isBold() : bool            // weight >= 600
->equals(FontFace) : bool
```

### `Pdf\Font\FontStyle`

`Regular` / `Bold` / `Italic` / `BoldItalic` — a shorthand over `FontFace`.
`FontStyle::of(bool $bold, bool $italic)`, `->face() : FontFace`.

---

## PDF import

`Pdf\Import\*` — a pure-PHP reader for **trusted, unencrypted** PDFs. Classic
and stream cross-references, object streams and `/Prev` chains are handled;
`/Encrypt` is rejected.

```php
Pdf\Import\PdfImportDocument::fromFile(string $path) : self
new PdfImportDocument(Pdf\Import\PdfReader $reader)
->pageCount() : int
->page(int $oneBasedIndex) : Pdf\Import\ImportedPage

Pdf\Import\PdfReader::fromFile(string $path) : self
new PdfReader(string $bytes)
->trailer() : Pdf\Import\PdfDictionary
->object(int $number) : mixed
->resolve(mixed $value) : mixed          // follows a PdfReference

Pdf\Import\ImportedPage (readonly):
  string $contentBytes                   // decoded page content stream(s)
  Pdf\Import\PdfDictionary $resources
  array{float,float,float,float} $boundingBox   // crop or media box
  int $rotation                          // 0 / 90 / 180 / 270
  array<int,mixed> $dependencies
  ->boxWidthPt() ->boxHeightPt()         // the box as authored
  ->widthPt() ->heightPt()               // after the page's own rotation
```

Placing an imported page (`$page->placePdf(...)`) emits it as a vector Form
XObject with its fonts/images/resources copied and renumbered. Source
annotations, links and form fields are not carried over.

---

## Exceptions

All extend `Pdf\Exception\PdfException` (which extends `\RuntimeException`).

| class | thrown when |
|---|---|
| `PdfException` | output can't be written; a stream can't be compressed; an imported PDF is malformed / encrypted / uses an unsupported stream filter |
| `FontException` | a font definition is missing / invalid / unresolvable |
| `ImageException` | an image can't be decoded or is an unsupported format |
| `LayoutException` | content can't be laid out — header + footer leave no room; an unsupported node type; pagination fails to terminate |
