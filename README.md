# declarative-pdf

A reimagining of FPDF as a **typed, declarative** PDF library with a real
block-layout engine.

Instead of driving a cursor (`AddPage` / `SetFont` / `Cell` / `Ln`), you
describe the document as an immutable tree of nodes and a layout engine places
them.

```php
use Pdf\Document;
use Pdf\Style\StylePatch;
use Pdf\Style\TextAlign;

Document::create()
    ->meta(fn ($m) => $m->title('Report')->author('Jo'))
    ->page(fn ($p) => $p
        ->heading(1, 'Overview')
        ->paragraph('A paragraph that wraps automatically across lines.')
        ->paragraph('Justified body text.', new StylePatch(align: TextAlign::Justify)))
    ->save('out.pdf');
```

The immutable tree can also be built directly:

```php
use Pdf\Node\{Document, Page, PageMaster, Heading, Paragraph, Meta};
use Pdf\Text\InlineSequence;

$tree = new Document(
    meta: new Meta(title: 'Report'),
    pages: [new Page(new PageMaster(), [
        new Heading(1, InlineSequence::of('Overview')),
        new Paragraph('Body text.'),
    ])],
);

file_put_contents('out.pdf', Document::render($tree)); // \Pdf\Document facade
```

## Requirements

- PHP 8.3+
- ext-zlib, ext-mbstring
- ext-gd for GIF / WebP images; ext-iconv for non-Windows-1252 font encodings

## Status — Phases 1–5

Implemented:

- Typed low-level PDF writer (objects, streams, Flate, xref, trailer) — ported
  from `fpdf.php` `_put*` / `_enddoc`.
- Core-font metrics and embedding, ToUnicode CMaps, `/Widths`, `/FontDescriptor`
  — ported from `_putfonts` / `_tounicodecmap`.
- Style resolution with inheritance, heading/paragraph defaults, and box
  properties (padding / border / background).
- Greedy line breaking across multi-style runs, with justification — ported
  from the `MultiCell` scan loop.
- A box model (`Box` / `StackBox` / `TextBox` / `ContainerBox` / `ListItemBox` …)
  with `split()`-based **multi-page pagination**: page-break trigger,
  orphan/widow control, `keepWithNext`, `keepTogether`, explicit `PageBreak`.
- Block flow: `Spacer`, `Rule`, `Container` (padding/border/background),
  `BulletList` / `OrderedList`.
- **Headers and footers** as `PageContext`-driven closures on the page master
  (replacing `Header()` / `Footer()` overrides); real total page count, no
  `{nb}` alias.
- **Images** — JPEG, PNG (incl. palette, colour-key and full alpha → soft
  mask), GIF and WebP (both via in-memory PNG re-encode; the WebP path fixes
  FPDF's alpha-losing JPEG conversion). Ported from `_parse*` / `_putimage`.
  Sources may be a path, an `http(s)://` URL, or a `data:` URI (URL fetch is
  dependency-free — streams, with an `ext-curl` fallback).
- **Links** — inline `withLink()` runs targeting a URI or an `#anchor`;
  `Anchor` nodes resolved to `/Dest` after layout; `/Annots` per page. Ported
  from `AddLink` / `SetLink` / `Link` / `_putlinks`.
- **Multi-column layout** — `Columns` block: balanced when it fits, filled
  column-then-page when it overflows (replaces the tuto4 `AcceptPageBreak`
  trick).
- **Tables** — `Table` / `TableRow` / `TableCell` with automatic column
  sizing (`ColumnWidth::auto()` / `fixed()` / `fraction()` + min/max clamps),
  a deterministic CSS-style autosizing algorithm (`Layout\TableLayout`),
  per-page row splitting with **repeating header rows**, oversized-row cell
  splitting, `colspan`, vertical alignment, per-cell background, grid borders.
  Replaces the hand-built `Cell()` grids of tuto5.
- **UTF-8 text** — API input is UTF-8 and is transcoded to each font's encoding
  before measuring and emitting (`Text\Encoding`): Windows-1252 via mbstring,
  the other `makefont` code pages (cp1250–1258, cp874, KOI8, ISO-8859-*) via
  iconv. Accents, the euro sign, em/en dashes and curly quotes render correctly.
- **Inline decorations** — `withBold` / `withItalic` / `withUnderline` /
  `withStrikethrough` / `withSuperscript` / `withSubscript` / `withBreak`
  (`<br>` without a paragraph split) / `withImage` (an image that flows with
  the text, sits on the baseline and grows the line height); `fontSizeScale`
  and `baselineShift` on `StylePatch`. The line breaker works on an item
  stream (`Layout\Inline\{Word,Space,Break,Box}Item`), not a byte loop.
- **Named stylesheets** — `Style\Stylesheet` per-type rules (`h1`, `paragraph`,
  `table`, …) applied between built-in defaults and the node's own patch.
- **Embedded fonts** — `FontRepository::register()` a TrueType/Type1 `.json`
  (from the `makefont` tool); subsetted program embedded with `/FontFile2`,
  `/FontDescriptor` and a ToUnicode CMap (tuto7). OpenType `.otf` with
  PostScript (CFF) outlines embeds whole as `/FontFile3` `/Subtype /Type1C`.
- **Inline HTML** — `Pdf\Text\Html::toInline()` / `$page->html()` for
  `b`/`i`/`u`/`s`/`sup`/`sub`/`a`/`br` (tuto6).
- **Large-format sheets + absolute area layout** — `PageSize::arch('e')` /
  `ansi()` / `a0()`; `$page->place()` / `placeImage()` / `placePdf()` /
  `frame()` position content in explicit rectangles with `Fit` + `BoxAlign`
  (blueprint / poster / plan-set layout). Block content shrinks to fit.
- **PDF page import** — `$page->placePdf('drawing.pdf', page: 1)` imports one
  page of an external (trusted, unencrypted) PDF as a **vector Form XObject**,
  copying its fonts/images/resources. `Pdf\Import\PdfReader` handles classic
  and stream xrefs, object streams and `/Prev` chains.
- `Document::create()` builder; file / string / HTTP output.

### Docs — [`docs/`](docs/)

- [Getting started](docs/getting-started.md) — install, first document, output
- [Cookbook](docs/cookbook.md) — recipes for every feature
- [API reference](docs/reference.md) — every method, node, option, enum
- [FPDF vs. declarative](docs/fpdf-vs-declarative.md) — the 7 tutorials, side by side
- [Porting from FPDF](docs/porting.md) — the concept mapping in prose
- [Architecture](docs/architecture.md) — the pipeline

Not yet implemented (see [`docs/roadmap.md`](docs/roadmap.md) for the full list,
sized and prioritised):

- interactive forms (AcroForm) and embedded JavaScript
- vector drawing primitives (paths / arcs / dashes) for authoring linework
- full document-to-document PDF merge (bookmarks, links, forms) — shell out to
  `qpdf` for that; the built-in importer is single-page-as-XObject
- outlines / bookmarks, tagged PDF / PDF-A, font subsetting, encryption
- import from encrypted PDFs
- stylesheet *class* selectors (per-node `StylePatch` covers the same need today)

## Development

```
composer install
composer test        # phpunit  (135 tests)
composer stan        # phpstan  (level 6)
php examples/hello.php  # also: report media table styled html custom-font sheet detail-sheet
UPDATE_GOLDENS=1 composer test   # refresh golden PDFs after an intended change
```

CI (`.github/workflows/ci.yml`) runs phpstan + phpunit on PHP 8.3 and 8.4, plus
a structural job that renders every example and runs `qpdf --check` /
`pdftotext` on the output.

## Attribution & licence

This is a from-scratch reimagining of FPDF. The high-level API and layout
engine are original; the low-level PDF writer, font metrics/embedding, ToUnicode
CMaps and image decoders were ported from FPDF 1.9 (comment citations
`fpdf.php:NNN` refer to that release). See [`NOTICE`](NOTICE).

Released under the MIT licence ([`LICENSE`](LICENSE)); the ported portions and
`tools/makefont/` retain FPDF's permissive licence ([`LICENSE-FPDF.txt`](LICENSE-FPDF.txt)).
All seven original FPDF tutorials are ported to `examples/`.

`tools/makefont/` is FPDF's offline builder for custom-font `.json` definitions:

```
php tools/makefont/makefont.php  MyFont.ttf  cp1252
# -> MyFont.json (+ MyFont.z) ; then FontRepository::register('MyFont', $style, 'MyFont.json')

php tools/makefont/makefont.php  MyFont.otf  cp1252
# PostScript (CFF) outlines -> MyFont.json (+ MyFont.cff.z), embedded whole (no subsetting)
```
