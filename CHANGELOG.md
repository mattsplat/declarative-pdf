# Changelog

All notable changes to this project are documented here. The format is based on
[Keep a Changelog](https://keepachangelog.com/en/1.1.0/), and this project
adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

While the version is `0.x`, minor releases may contain breaking changes.

## [Unreleased]

## [0.3.0] - 2026-09-04

A single-feature release: reading text back out of a PDF. No engine or writer
behaviour changed — every 0.2.0 golden is byte-identical.

### Added

- **Text extraction** — `Pdf\Import\TextExtractor::fromFile($path)->text()` /
  `->pages()`, and `ImportedPage::extractText()`. Best-effort: decodes through
  each font's `/ToUnicode` CMap (new `Pdf\Import\ToUnicodeCmapParser`) or
  WinAnsi otherwise, using glyph widths to place spaces and line breaks. Does
  not descend into Form XObjects, so a page already placed into another
  document via `placePdf()` won't yield text — extract from the source PDF
  directly. `/Type0` composite fonts decode only via `/ToUnicode`,
  `/Differences` encodings fall back to WinAnsi, and reading order is
  top-to-bottom as drawn rather than column-aware.
- `PdfParser::readBareWord()` — factored out of its existing keyword parser so
  the new content-stream tokenizer reuses it.
- Example: `extract-text.php`.

## [0.2.0] - 2026-08-31

A component release: reusable blocks and builders that sit on top of the 0.1.0
engine, plus page transitions for slide decks. No engine or writer behaviour
changed — every 0.1.0 golden is byte-identical.

### Added

- **Absolute-layout kit** — compose sheet layouts without hand-computed
  coordinate arithmetic:
  - `Pdf\Layout\Grid` (`$page->grid()` / `Grid::inside()`) splits a rectangle
    into sub-rectangles by weight (`->rows()` / `->columns()`) or by a mix of
    fixed and fractional `Pdf\Layout\Track`s (`->rowTracks()` /
    `->columnTracks()`); every slice is itself a grid, so bands nest.
  - `Pdf\Builder\Panel` — a bordered, inset region holding a PDF page, an image,
    or block content (`->showing()` dispatches by source type, `->containing()`
    takes blocks); `Panel::in()` accepts a grid rectangle directly.
  - `PageBuilder::hline()` / `vline()` draw hairline divider rules;
    `PageBuilder::writableRectPt()` exposes the area inside the margins in
    points, and `PageBuilder::unit()` reads back the coordinate unit.
  - `Pdf\Support\Source::first()` resolves the first usable asset from a list of
    local paths and `http(s)://` URLs, with a fallback.
- **Flow-content components** — reusable `Pdf\Node\Component`s that expand to
  existing nodes, so they compose anywhere a block does:
  - `Pdf\Node\DefinitionList` — term/body pairs as a borderless two-column
    table; accepts a `term => body` map or `[term, body]` pairs.
  - `Pdf\Node\Row` — a horizontal stack (children side by side, per-child
    widths, a gap, cross-axis alignment).
  - `Pdf\Node\Card` — titled, framed content with an optional under-rule.
  - `Pdf\Node\Callout` — tinted content with a single-edge accent border.
  - `DocumentBuilder::cover()` + `Pdf\Builder\CoverBuilder` — prepend a cover
    page (title, subtitle, logo, caption lines, its own page size, and a
    `Centered` / `TopLeft` / `BottomBand` preset). The cover keeps an inherited
    watermark but drops inherited page numbers; `->bare()` drops both.
- **Data-driven tables** — `Pdf\Builder\DataTable` + `Pdf\Builder\Total`
  (`$page->dataTable(...)`): feed a row collection and column specs (key,
  header, alignment, width, a value-formatting callback); get automatic
  per-group subtotals and a grand total (`sum` / `avg` / `count` / a label /
  a callback), with `->groupBy()` emitting group-header rows. Emits the
  existing `Table` node, so pagination and column sizing are unchanged.
- **Page transitions & presentation mode** — `Pdf\Node\Transition` (`split`,
  `blinds`, `box`, `wipe`, `dissolve`, `glitter`, `fade`, `push`, `cover`,
  `uncover`, `fly`), set per page with `PageBuilder::transition()`.
  `PageBuilder::autoAdvance($seconds)` emits `/Dur`;
  `DocumentBuilder::presentation($advanceSeconds = null)` opens the document
  full-screen. Transition direction is a style-scoped enum
  (`WipeDirection` / `GlitterDirection` / `PushDirection`), so an out-of-spec
  `/Di` will not compile. The PDF header is bumped to `1.5` only when a page
  carries a transition that needs it.
- Examples: `grid.php`, `components.php`, `data-table.php`, `slides.php`.

### Changed

- `Pdf\Style\Edge` moved to **`Pdf\Geometry\Edge`** — a single box side is a
  geometry concept, and `Style\Edge` one namespace from `Geometry\Edges` read
  as a typo waiting to happen. Update any `use Pdf\Style\Edge;`.
- `DocumentRenderer` now emits `%PDF-1.5` for documents that use a 1.5-era
  transition; documents without transitions are unaffected.

## [0.1.0] - 2026-08-29

First tagged release. A from-scratch reimagining of FPDF 1.9 as a typed,
declarative library: you describe a document as an immutable tree of nodes and a
`measure → paginate → render → serialise` pipeline places everything.

### Added

- **Layout engine** — greedy line breaking, box-model pagination (split /
  keep-together / keep-with-next / widow–orphan), headers and footers,
  multi-column blocks.
- **Text & typography** — UTF-8 with per-font transcoding, inline decorations,
  sub/superscript, inline HTML (`b`/`i`/`u`/`s`/`sup`/`sub`/`a`/`br`), named
  stylesheets with class rules, public text/block measurement helpers.
- **Fonts** — the 14 core fonts plus embedded TrueType (subsetted) and
  OpenType/CFF; named and numeric font weights via `FontFace`.
- **Tables** — automatic column sizing, fixed / auto / fraction widths,
  repeating headers, colspans, per-cell alignment and backgrounds.
- **Images** — JPEG, PNG (with SMask), GIF and WebP, from a path, an
  `http(s)://` URL or a `data:` URI; block and inline placement.
- **Vector drawing** — `Path` with `moveTo`/`lineTo`/`curveTo`/`close`, solid or
  gradient fills (axial `/Shading` type 2, radial type 3), stroke, fill rules,
  caps and joins; `Clip` for masking to an arbitrary path.
- **Charts** — a thin `Chart` node over the path API: bar, line, pie, sparkline
  with a nice-number value axis and legends.
- **Interactive forms** — `TextField`, `Checkbox`, `RadioGroup`, `Dropdown`,
  `ListBox`, `PushButton`, `SignatureField` as `/AcroForm` fields with
  self-drawn `/AP` appearance streams (no `/NeedAppearances`); native
  `/SubmitForm` and `/ResetForm`; an opt-in Acrobat JavaScript layer
  (`Pdf\Interactive\Js`, `/AA`, `/CO`, document-level `/Names /JavaScript`).
- **Navigation & metadata** — internal and external links, an outline / bookmark
  tree, an Info dictionary.
- **Large-format & absolute layout** — `PageSize::arch()` / `ansi()` / `a0()`,
  `place()` / `placeImage()` / `placePdf()` / `frame()` with `Fit` + `BoxAlign`
  and `ShrinkMode`.
- **PDF import** — a pure-PHP reader for trusted, unencrypted PDFs; one page
  imported as a vector Form XObject.
- **Determinism** — with a `FixedClock`, `compress: false` and a fixed producer
  string the output is byte-stable; golden-file tests depend on it.

[Unreleased]: https://github.com/mattsplat/declarative-pdf/compare/v0.3.0...HEAD
[0.3.0]: https://github.com/mattsplat/declarative-pdf/compare/v0.2.0...v0.3.0
[0.2.0]: https://github.com/mattsplat/declarative-pdf/compare/v0.1.0...v0.2.0
[0.1.0]: https://github.com/mattsplat/declarative-pdf/releases/tag/v0.1.0
