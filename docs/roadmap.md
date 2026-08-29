# Roadmap

Where the library could go next. Grouped by area, ordered loosely by how well
each fits the current architecture. Effort is a rough T-shirt size: **S** ≈ a
day, **M** ≈ a few days, **L** ≈ a week+, **XL** ≈ a project of its own.

While the version is `0.x`, minor releases may contain breaking changes.

**Shipped in 0.1.0** — see [`CHANGELOG.md`](../CHANGELOG.md) for the full list:
the box-model layout engine (line breaking, pagination, keep rules, headers and
footers), core + embedded TrueType/CFF fonts with numeric weights, auto-sized
tables with repeating headers, JPEG/PNG/GIF/WebP images, inline HTML, stylesheets
with class rules, reusable `Component` nodes, text/block measurement helpers,
page-number and watermark helpers, large-format and absolute-area layout, outline
bookmarks, `Path` vector drawing with gradient fills and path clipping, the
`Chart` node (bar / line / pie / sparkline), `/AcroForm` fields with self-drawn
appearance streams, an opt-in document/field JavaScript layer, and a pure-PHP
single-page PDF importer.

---

## Near-term order

1. **FDF / XFDF read + write** (S each) — serialise a data array to FDF/XFDF and
   parse a filled one back. The native submit / reset buttons already exist; this
   is a small standalone (de)serialiser.
2. **CFF font subsetting** (L–XL) — a CFF cut currently embeds whole (40–80 KB).
   Rebuild the CharStrings INDEX, charset and subrs. Shrinks every CFF-font
   document; no API change.
3. **`bookmarksFromHeadings()` + running headers + table of contents** (M
   together) — all three want the same post-pagination heading-marks pass. Do it
   once and land the three features on top.
4. **XMP metadata** (S) — a `/Metadata` packet mirroring the Info dict plus
   Dublin Core. Cheap on its own and a prerequisite for the compliance track.
5. **Transparency & blend modes** (S) — `/ExtGState` `/CA` `/ca` `/BM`, an
   `opacity` field through `StylePatch` and the placement API.
6. **Tagged PDF + PDF/A** (L–XL) — the accessibility / archival track, once XMP
   and colour management are in place. Only if the audience needs it.

---

## Vector drawing

Shipped: `Path` (`moveTo` / `lineTo` / `curveTo` / `close`, solid or gradient
fill, stroke, `FillRule`, `LineCap`, `LineJoin`), `Clip` for path clipping
regions, and the `Chart` node. What is left:

- **Dash arrays** (`d`) and **miter limit** (`M`) — **S**.
- **Per-subpath gradients** and **tiling patterns** (`/Pattern` colour space,
  `scn`) — **S–M**; the shading path uses `sh` rather than a shading pattern.
- **Per-subpath paint** and **auto bounding-box sizing** (the author states the
  box today) — **S each**.
- **Node transforms** (rotation / skew) — **S**; `cm` is already emitted for
  placed areas.
- Rounded rectangles, polylines and arcs as further factories — **S**.
- **Chart** additions — **S each**: stacked / 100 % bars, area fill, scatter,
  donut; a second value axis, log scale, explicit bounds / tick count; data
  labels on points and bars; value-formatting callbacks.
- **Transparency & blend modes** — **S**: `/ExtGState` `/CA` / `/ca` (stroke /
  fill alpha) and `/BM`. The image path already builds SMasks; this is the
  general case.

---

## Text & typography

Shipped: the 14 core fonts, embedded TrueType (subsetted), OpenType/CFF
(embedded whole), named / numeric weights via `FontFace`. What is left:

- **CFF subsetting** — **L–XL** (see the near-term list).
- **Synthetic bold / oblique** — **M**: faux-bold via text render mode 2, faux
  oblique via a shear, for a family shipping only one cut (there is a `// TODO`
  on `FontRepository::registeredPath()`).
- **OpenType shaping & complex scripts** — **XL**: CID-keyed `/Type0` composite
  fonts (CJK, any script needing >256 glyphs), ligatures and kerning
  (`GPOS` / `GSUB`), right-to-left and Indic (bidi reordering, mark
  positioning). This is where a dependency (HarfBuzz via FFI, or a pure-PHP
  shaper) becomes worth considering.
- **Typographic polish** — **M**: hyphenation (Liang's algorithm + language
  patterns), non-greedy line breaking (Knuth–Plass) as an opt-in, small caps,
  letter-spacing (`Tc`) and word-spacing on `StylePatch`, tab stops / leader
  dots, drop caps, baseline-grid alignment.
- **Footnotes, endnotes, margin notes** — **L**: a footnote reserves space at
  the bottom of the page it lands on, which feeds back into pagination (a
  two-way constraint the one-pass splitter doesn't have). Endnotes are easier
  (collected, emitted at the end); margin notes need a side band in the page
  geometry.
- **Running headers from content** — **M**: `$ctx->runningHeading` populated from
  the last heading at or before the top of the page. Needs the heading-marks
  pass.
- **Table of contents** — **M**: a `TableOfContents` node that walks the heading
  marks after layout and emits a generated list with leader dots, real page
  numbers and an internal link per entry.

---

## Document structure & metadata

Shipped: the Info dictionary, explicit outline bookmarks
(`DocumentBuilder::bookmark()` → an `/Outlines` tree with `/Prev` / `/Next` /
`/First` / `/Last` / `/Count` and a per-item `/Dest`). What is left:

- **`bookmarksFromHeadings(maxLevel: 3)`** — **S**: auto-generate the outline
  from heading marks (shares the pass the TOC wants).
- **Outline extras** — **S**: `/PageMode /UseOutlines` to open the panel on
  load, collapsed `/Count` (negative), coloured / bold items (`/C`, `/F`).
- **XMP metadata** — **S**: a `/Metadata` XML stream mirroring the Info dict plus
  Dublin Core. Required for PDF/A, expected by DAM systems.
- **Tagged PDF / PDF-UA** (accessibility) — **XL**: a structure tree
  (`/StructTreeRoot`, `/MarkInfo`), `BDC` / `EMC` around every run and figure,
  `/Alt` on images, reading order, `/Lang`. A large cross-cutting change; high
  value for government / enterprise / EU-accessibility contexts.
- **PDF/A & PDF/X conformance** — **L** (needs XMP + tagging + colour): no JS, no
  encryption, all fonts embedded, an OutputIntent with an ICC profile, `/ID` in
  the trailer; CMYK / spot colour and trim/bleed boxes for PDF/X; plus a
  conformance-checker render mode.
- **Optional content (layers)** — **M**: `/OCProperties` + `/OC` on content
  groups. Fits the placement API.
- **File attachments** — **S**: `/EmbeddedFiles` name tree + `/Filespec`, plus
  `FileAttachment` pin annotations.
- **Page labels** — **S**: `/PageLabels` number tree (roman front matter, then
  arabic).
- **Article threads, page transitions, viewer prefs** — **S each**:
  `/PageLayout`, `/PageMode`, `/ViewerPreferences`, `/Trans`, `/Threads`.

---

## Interactive forms

Shipped: `TextField` / `Checkbox` / `RadioGroup` / `Dropdown` / `ListBox` /
`PushButton` / `SignatureField` as `/AcroForm` with self-drawn `/AP /N`
appearance streams (no `/NeedAppearances`), native `/SubmitForm` / `/ResetForm`,
and an opt-in JavaScript layer (`Pdf\Interactive\Js` — sum / product / average /
min / max / range-validate / currency-number-percent formatting, `Js::raw()`;
`/AA`, `/CO`, document-level `/Names /JavaScript`). See [`forms.md`](forms.md).
The viewer-support caveat for the JS layer is documented in the `Js` class.

What is left:

- **FDF / XFDF read + write** — **S each** (see the near-term list).
- **Digital signatures** — **XL**: a `/Sig` field with a PKCS#7 / CMS signature,
  `/ByteRange`, incremental-update save so the signed bytes stay stable. Needs a
  crypto backend (`openssl` / `ext-sodium`), an RFC 3161 timestamp authority for
  LTV, and PAdES-baseline profiles for EU compliance. The visible appearance is
  easy; the cryptography and byte-exact incremental save are the hard part.

---

## Annotations (non-link)

- **Markup annotations** — **M**: `/Text` (sticky note), `/Highlight` /
  `/Underline` / `/StrikeOut` / `/Squiggly`, `/Square` / `/Circle`, `/Line`,
  `/Polygon` / `/PolyLine`, `/Ink`, `/FreeText`, `/Stamp`. Each needs an
  appearance stream. Useful for generated review copies.
- **Redaction** — **L**: `/Redact` annotations *plus* actually removing the
  underlying content from the stream — not just drawing a black box. The
  box-only version is **S** and dangerous (data still there); real redaction
  needs content-stream surgery.

---

## Security

- **Encryption** — **L**: RC4 (40/128-bit) and AES (128/256-bit, `/V 5 /R 6`)
  standard security handler; owner / user passwords, permission flags. Also
  unlocks *reading* encrypted source PDFs in the importer (currently rejected).
  Public-key (certificate) encryption is a further **M**.
- **Digital signatures** — **XL** (see [Interactive forms](#interactive-forms)).

---

## Import / merge

Shipped: one page imported as a vector Form XObject with its
fonts/images/resources copied and renumbered; watermarks / overlays on
*generated* pages. What is left:

- **Multi-page & full-document native merge** — **L**: import a page range or a
  whole document; `$doc->append('other.pdf')` / `->prepend()` /
  `->insertAfter(page: 3, …)` where imported pages become *real* pages, not
  stamped XObjects; carry over outlines (remapped), internal links, page labels,
  form fields, annotations, named destinations; deduplicate shared resources.
  The parser already handles xref streams, object streams and `/Prev` chains.
- **Stamping imported pages / n-up** — **M**: overlay / underlay onto pages of an
  *imported* PDF; 2 / 4 / 8-up imposition; page resize / crop / rotate on import.
- **Split / extract** — **S**: `Pdf::split('in.pdf')` → per-page files;
  `extract(pages: [2,5,7])`. Mostly importer plumbing.

---

## Images & colour

- **CMYK & spot colour** — **M**: `Color::cmyk()` and `Color::spot(name, tint)`
  (Separation colour space); add `k` and `/SepName cs` to the content-stream
  colour ops. Prerequisite for PDF/X.
- **ICC colour management** — **M**: `/ICCBased` colour spaces, embedded input
  profiles on images, `/OutputIntent`. Pairs with PDF/A / PDF/X.
- **More image formats** — **S–M**: TIFF (LZW / PackBits / G4), BMP, AVIF /
  JPEG 2000 (`/JPXDecode`); SVG → rasterise, or better → native vector via the
  drawing primitives (**L**, needs an SVG path parser).
- **Image reuse & downsampling** — **S**: deduplicate identical images by hash
  (a repeating-header logo embedded once); an optional DPI ceiling that
  downsamples on the way in.

---

## Layout engine

- **Context-aware components** — **M**: a `PageAware` variant expanded per
  physical sheet by the `Paginator` — `body(PageContext $ctx)` — unlocking
  "Page X of Y" *inside* body flow, running headers, and a generated TOC.
  Interacts with the two-pass page-count model.
- **Absolutely / relatively positioned blocks in flow** — **M**:
  `position: absolute | relative` on a block within normal flow. Today absolute
  placement is only the separate `place()` area API.
- **Floats & text wrap around images** — **L**: `float: left | right` with text
  flowing around the float; the line breaker would need a per-line available
  width that varies with y.
- **CSS-grid / flex regions** — **L**: a real 2-D region layout beyond `Columns`
  (named areas, spanning).
- **Bleed / trim / crop boxes** — **S**: `/BleedBox` / `/TrimBox` / `/ArtBox`
  plus crop-mark generation. Needed for professional print.
- **Widow/orphan & keep across nested structures** — **M**: extend
  keep-together / keep-with-next to work reliably through arbitrarily nested
  containers, lists and table row groups.

---

## Tooling & developer experience

- **Markdown → document** — **M**: `Pdf\Markdown::toDocument()`, a CommonMark
  front-end producing the node tree. The inline-HTML helper is a partial
  precedent.
- **Templating / data binding** — **M**: a `Template` taking a tree with
  placeholders + a data collection, producing N documents (mail merge); or a
  Twig / Blade bridge.
- **Streaming / incremental output** — **L**: emit the PDF page-by-page to a
  stream instead of buffering the whole document. Conflicts somewhat with the
  two-pass "layout everything, then render" design; needs a bounded look-ahead
  mode.
- **Diagnostics** — **S**: a "layout overlay" debug mode (box edges, baselines,
  margins, break points); structural self-validation; warnings surfaced as a
  collectable list rather than exceptions.
- **Performance** — **M**: cache `FontMetrics::stringWidth` per (font, size,
  string) run; reuse measured boxes across the two pagination passes; profile
  the line breaker on large documents.
- **Code style** — **S**: adopt Laravel Pint (or a PHP-CS-Fixer PHAR in CI) and
  a `composer lint` script, so style stops being "match the surrounding code
  by hand".
