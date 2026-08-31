# Changelog

All notable changes to this project are documented here. The format is based on
[Keep a Changelog](https://keepachangelog.com/en/1.1.0/), and this project
adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

While the version is `0.x`, minor releases may contain breaking changes.

## [Unreleased]

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
    points.
  - `Pdf\Support\Source::first()` resolves the first usable asset from a list of
    local paths and `http(s)://` URLs, with a fallback.

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

[Unreleased]: https://github.com/mattsplat/declarative-pdf/compare/v0.1.0...HEAD
[0.1.0]: https://github.com/mattsplat/declarative-pdf/releases/tag/v0.1.0
