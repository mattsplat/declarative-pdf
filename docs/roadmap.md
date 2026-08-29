# Potential features

Where the library could go next. Ordered loosely by how well each fits the
current architecture. Effort is a rough T-shirt size: **S** ≈ a day, **M** ≈ a
few days, **L** ≈ a week+, **XL** ≈ a project of its own.

Status today (Phases 1–5): typed writer, core + embedded fonts (TrueType and
OpenType/CFF) with named / numeric weights (`FontFace`), style resolution,
greedy line breaking, box-model pagination (split / keep-together /
widow-orphan / keep-with-next), headers/footers, images (JPEG/PNG/GIF/WebP with
SMask, from a path / URL / `data:` URI), internal + external links, outline
bookmarks, multi-column blocks, auto-sized tables with repeating headers, UTF-8
encoding, inline decorations, inline HTML, named stylesheets with class rules,
large-format sheets, absolute area placement (`place()` with `ShrinkMode`
geometric or font-size fit), page-number and watermark helpers, public text /
block measurement helpers
(`PageBuilder::textWidth()` / `measureBlocks()`, `Pdf\Text\TextMeasurer`),
reusable `Component` nodes, vector drawing (`Path` with solid fill / stroke),
a pure-PHP single-page PDF importer emitting vector Form XObjects, and
interactive **AcroForm fields** with self-drawn appearance streams (text,
checkbox, radio, dropdown, list box, push / submit / reset buttons, signature
placeholders).

The [`plans/fonts-and-measurement.md`](plans/fonts-and-measurement.md) plan —
OpenType/CFF embedding, named weights, `place()` shrink-to-fit, `textWidth` /
`measureBlocks` — has shipped; it stays as a design record.

---

## Interactive forms & JavaScript

> A dedicated feature-by-feature breakdown — conditional forms, calculators,
> configurators, quizzes, signatures, layers, 3D, dynamic tables, the event
> model — with difficulty **and viewer-reach** ratings is in
> [`interactive-pdf-feasibility.md`](interactive-pdf-feasibility.md).

### AcroForm fields — **done**

Native PDF form fields. New `Node` types placed in block flow *or* via
`$page->place()` (they implement `Pdf\Node\FormField`):

| Node | PDF field type | Notes |
|---|---|---|
| `TextField` | `/Tx` | single- / multi-line, max length, comb, password, `/Q` alignment |
| `Checkbox` | `/Btn` | on/off, export value, inline label |
| `RadioGroup` / `RadioOption` | `/Btn` | shared field name, per-option export value, options stack one per row |
| `Dropdown` | `/Ch` combo | editable or fixed list, `/Opt` pairs |
| `ListBox` | `/Ch` list | single / multi select |
| `PushButton` | `/Btn` | `ButtonKind::Push` / `Submit` (`/SubmitForm`) / `Reset` (`/ResetForm`) |
| `SignatureField` | `/Sig` | empty placeholder; `/SigFlags 3`; signing is separate |

What it does:

- Adds `/AcroForm` to the catalog: `/Fields`, `/DR` (default resource dict
  listing every used font), `/DA` (default appearance), `/SigFlags` when a
  signature field is present. `/NeedAppearances` is **not** emitted.
- Each widget is a `/Subtype /Widget` annotation written through the same
  per-page `/Annots` path as links (`AcroFormWriter::plan()` reserves the object
  numbers before the page loop; `write()` emits the bodies afterwards). Widget
  `/Rect` goes through `PageGeometry::flipY()`, like link annotations.
- **Self-drawn `/AP /N` appearance XObjects** (`Pdf\Interactive\AppearanceStream`):
  border, background and value text in the widget's own form space. Checkboxes
  and radios carry `/Off` plus an on-state; the on-state paints a vector check
  or dot. Correct in every viewer, no `/NeedAppearances`.
- Field flags (`/Ff`) via `Pdf\Interactive\FieldFlag`: required, read-only,
  multiline, password, do-not-scroll, comb, radio, combo, multi-select, sort.
- `/AcroForm /CO` calculation-order array — wired but empty until JS lands.

Self-contained: a fillable, printable, saveable form needs no JavaScript.
See `examples/form.php` and [`forms.md`](forms.md).

### Document & field JavaScript — **M** (on top of AcroForm)

- **Document-level JS**: `/Names /JavaScript` name tree in the catalog; each
  entry is `<< /S /JavaScript /JS (…) >>`. Runs on open. Good for defining
  shared functions.
- **Field actions** (`/AA` additional-actions dict on a widget/field):
  - `/K` keystroke, `/F` format, `/V` validate, `/C` calculate
  - `/A` primary action (e.g. a button that runs `this.print()` or submits)
- **API shape**: `new TextField(name: 'total', calculate: Js::sum('qty', 'price'))`
  or raw `Js::raw('event.value = …')`. A small `Pdf\Interactive\Js` helper with
  a few canned recipes (sum, product, average, validate-range, format-currency)
  covering 90% of real use, plus a raw escape hatch.

**Honest caveats — read before committing to JS:**

- **Viewer support is the whole story.** Adobe Acrobat / Reader run PDF
  JavaScript fully. Chrome (pdfium), macOS Preview, and Firefox's pdf.js run
  little to none — calculated and validated fields simply won't update there.
  If the audience opens PDFs in a browser, JS calculations are invisible to
  them.
- Many enterprises disable PDF JavaScript by policy (it has a long history of
  being a malware vector). Content that *depends* on it will silently misbehave.
- It complicates accessibility and long-term archival (PDF/A forbids JS
  entirely).

**Recommendation:** build AcroForm fields with self-generated appearance
streams first — that delivers fillable/printable/saveable forms that work in
every viewer. Add the JavaScript layer as an opt-in on top for the
Acrobat-centric workflows that need live calculations, and document the viewer
limitation prominently.

### Form submission & FDF/XFDF — **S**

Once fields exist: `SubmitButton(url: …, format: Fdf|Xfdf|Html|Pdf)` →
`/A << /S /SubmitForm /F (url) /Flags n >>`. Also `ResetButton`. Reading form
data back (parsing a filled FDF/XFDF) is a separate small parser.

---

## Vector drawing

### Drawing primitives — **done** (solid paint)

`Pdf\Node\Path` is a first-class block node: an ordered list of
`Pdf\Geometry\PathCommand`s (`moveTo` / `lineTo` / `curveTo` / `close`, one
flat list describing any number of subpaths) painted with a solid
`Pdf\Style\Paint` — fill colour, stroke colour, stroke width, `FillRule`
(nonzero / even-odd), `LineCap`, `LineJoin`. Static factories cover `line`,
`rectangle`, `ellipse` (4-Bézier approximation) and `polygon`; `Path::of()`
takes a hand-built command list. Coordinates are box-relative and top-left, in
the caller's `Unit`. Works in block flow and in `$page->place()`.
See `examples/shapes.php`.

What is left:

- **Gradients** — **M**: `/Shading` type 2 (axial) / type 3 (radial) as a fill,
  plus the `/Pattern` colour space and `sh`.
- **Clipping paths** (`W` / `W*`) — **S**: `ContentStream::withClip()` already
  clips to a rectangle; the general case is the same code taking a command list.
- **Dash arrays** (`d`) and **miter limit** (`M`) — **S**.
- **Per-subpath paint** and **auto bounding-box sizing** (the author states the
  box today) — **S each**.
- **Transforms** (rotation / skew on the node) — **S**; `cm` is already emitted
  for placed areas.
- Rounded rectangles, polylines and arcs as further factories — **S**.

### Charts / sparklines — **M**

Not core, but a thin `Pdf\Chart` built on the (now shipped) path API: bar,
line, pie, sparkline — axes, ticks, labels and a legend. Deterministic, no
external deps. `examples/shapes.php` hand-builds a bar chart today.

### Transparency & blend modes — **S**

`/ExtGState` with `/CA` / `/ca` (stroke / fill alpha) and `/BM` (blend mode).
Wire an `opacity` field through `StylePatch` and the placement API. The image
path already builds SMasks; this is the general case.

---

## Text & typography

### Font subsetting for embedded TrueType — **L**

Today an embedded font ships its whole glyf table. Real subsetting (keep only
used glyphs, rebuild `loca` / `glyf` / `cmap` / `hmtx`, fix `maxp`) can cut
attachment size 10–50×. Self-contained, heavily testable, no API change.

### OpenType / CFF embedding — **done** (simple fonts)

`makefont` accepts `OTTO` files: the `CFF ` table is lifted out verbatim and
embedded as `/FontFile3` `/Subtype /Type1C` under a `/Type1` font dict, 256
glyphs, WinAnsi + `/Differences`. What is left:

- **CFF subsetting** — **L–XL**: rebuild the CharStrings INDEX, charset and
  subrs. Today a cut embeds whole (40–80 KB).
- **CID-keyed CFF / `/CIDFontType0`** — part of the XL below; the tool rejects
  those files with a clear error.

### Named / numeric font weights — **done**

`Pdf\Font\FontFace(int $weight = 400, bool $italic = false)` is the resolution
key; `StylePatch(weight: 600)` (and `bold: true` ≡ 700) select a cut, and
`FontRepository::register('Family', new FontFace(600), …)` registers one.
Unregistered weights fall down a nearest-cut ladder; core families keep their
bundled bold/italic. What is left:

- **Synthetic bold / oblique** — **M**: faux-bold via text render mode 2, faux
  oblique via a shear, for a family that ships only one cut (there is a `// TODO`
  on `FontRepository::registeredPath()`).

### OpenType shaping & complex scripts — **XL**

- CID-keyed `/Type0` composite fonts — required for CJK and any script needing
  >256 glyphs.
- Ligatures, kerning (`GPOS` / `GSUB`), contextual forms.
- Right-to-left (Arabic / Hebrew) and complex scripts (Indic) — bidi
  reordering, the Unicode bidi algorithm, mark positioning.

This is where a dependency (HarfBuzz via FFI, or a pure-PHP shaper) becomes
worth considering. Very large.

### Typographic polish — **M**

- Hyphenation (Liang's algorithm + language pattern files) at line breaking.
- Non-greedy line breaking (Knuth–Plass / "best fit") as an opt-in — the
  current engine is deliberately greedy; KP would need paragraph-level
  optimisation and pushes back on pagination.
- Small caps, letter-spacing (`Tc`) and word-spacing controls on `StylePatch`.
- Tab stops / leader dots.
- Drop caps.
- Baseline grid alignment.

### Footnotes, endnotes, margin notes — **L**

Genuinely new layout machinery: a footnote reserves space at the bottom of the
*page it lands on*, which feeds back into pagination (a two-way constraint the
current one-pass splitter doesn't have). Endnotes are easier (collected, emitted
at the end). Margin notes need a side band in the page geometry.

### Running headers from content — **M**

"Section title in the header, updated per page" (like a dictionary's guide
words). The header closure gets `$ctx->pageCount` today; add
`$ctx->runningHeading` populated from the last heading that started at or before
the top of the page. Needs a marks-collection pass after pagination.

### Table of contents — **M**

`TableOfContents` node: after layout, walk the heading marks (page + y already
known for anchors) and emit a generated list with leader dots and real page
numbers, each entry an internal link. Depends on the anchor-resolution pass that
already exists.

---

## Document structure & metadata

### Outlines / bookmarks — **done** (explicit)

`DocumentBuilder::bookmark('Chapter 1', 'ch1', level: 0)` accumulates outline
entries; the renderer emits an `/Outlines` tree in the catalog with
`/Prev` / `/Next` / `/First` / `/Last` / `/Count` links and a
`/Dest [<page> 0 R /XYZ 0 <y> null]` per item, resolved through the same
anchor→(page, y) map the internal links use. Nesting is by `level` in call
order. An unresolved anchor throws `Pdf\Exception\PdfException`. What is left:

- **`bookmarksFromHeadings(maxLevel: 3)`** — **S**: auto-generate the outline
  from heading marks. Needs the heading-mark collection pass that the
  [Table of contents](#table-of-contents--m) item also wants.
- `/PageMode /UseOutlines` to open the panel on load, collapsed `/Count`
  (negative), and coloured / bold items (`/C`, `/F`).

### XMP metadata — **S**

An XMP packet (`/Metadata` stream, `/Type /Metadata /Subtype /XML`) mirroring
the Info dict plus Dublin Core. Required for PDF/A and generally expected by
DAM systems.

### Tagged PDF / PDF/UA (accessibility) — **XL**

A structure tree (`/StructTreeRoot`, `/MarkInfo /Marked true`), marked-content
operators (`BDC` / `EMC`) around every text run and figure, `/Alt` text on
images, reading order, `/Lang`. This is a large cross-cutting change — every
render path emits marked content and registers a structure element. High value
for government / enterprise / EU-accessibility-law contexts.

### PDF/A & PDF/X conformance — **L** (needs XMP + tagging + color)

- PDF/A-1b/2b/3b: no JS, no encryption, all fonts embedded, XMP present,
  OutputIntent with an ICC profile, `/ID` in the trailer.
- PDF/X for print: CMYK / spot color, OutputIntent, trim/bleed boxes.
- A conformance *checker* mode that fails the render if a rule is violated.

### Optional content (layers) — **M**

`/OCProperties` + `/OC` on content groups. Toggle-able layers (e.g. blueprint
annotations, multi-language overlays). Fits the placement API well.

### File attachments / embedded files — **S**

`/EmbeddedFiles` name tree + `/Filespec`. Attach source data, a spreadsheet,
etc. PDF/A-3 allows this. Also `FileAttachment` annotations (a pin icon on the
page).

### Page labels — **S**

`/PageLabels` number tree — roman numerals for front matter, then arabic, etc.
Currently pages are just 1..N.

### Article threads, page transitions, viewer prefs — **S each**

Small catalog-level extras: `/PageLayout`, `/PageMode`, `/ViewerPreferences`
(hide toolbar, fit window, print scaling), `/Trans` slideshow transitions,
`/Threads` for magazine-style article flow.

---

## Annotations (non-link)

### Markup annotations — **M**

`/Text` (sticky note), `/Highlight` / `/Underline` / `/StrikeOut` / `/Squiggly`,
`/Square` / `/Circle`, `/Line`, `/Polygon` / `/PolyLine`, `/Ink` (freehand),
`/FreeText` (text box on the page), `/Stamp`. Each needs an appearance stream.
Useful for generated review copies / redline documents.

### Redaction — **L**

`/Redact` annotations *plus* actually removing the underlying content (text,
images) from the content stream — not just drawing a black box over it. The
"draw a box" version is **S** and dangerous (data still there); real redaction
needs content-stream surgery.

---

## Security

### Encryption — **L**

- RC4 (40/128-bit) and AES (128/256-bit, `/V 5 /R 6`) standard security
  handler. Owner / user passwords, permission flags (print, copy, modify,
  annotate).
- Also enables *reading* encrypted source PDFs in the importer (currently
  rejected outright).
- Public-key (certificate) encryption is a further **M**.

### Digital signatures — **XL**

`/Sig` field with a PKCS#7 / CMS signature, `/ByteRange`, incremental-update
save so the signed bytes stay stable. Needs a crypto backend (`openssl` /
`ext-sodium`), timestamp authority (RFC 3161) support for LTV, and
PAdES-baseline profiles for EU compliance. Visible signature appearance is easy;
the cryptography and the byte-exact incremental save are the hard part.

---

## Import / merge

### Multi-page & full-document import — **L**

Today the importer pulls **one page as a Form XObject**. Extend to:

- Import a page *range* or a whole document in one call.
- `$doc->append('other.pdf')` / `->prepend()` / `->insertAfter(page: 3, …)` —
  true page-level merge where imported pages become real pages, not XObjects
  stamped onto new ones.
- Carry over: outlines (remapped), internal links, page labels, form fields,
  annotations, named destinations.
- Deduplicate shared resources (fonts, images) across merged documents.

This is the plan's "shell out to qpdf" item done natively. The parser
(`Pdf\Import\PdfReader`) already handles xref streams, object streams and
`/Prev` chains, so the reading half is mostly there.

### Stamping imported pages / n-up — **M**

Text/image watermarks on *generated* pages shipped (`PageBuilder::watermark()`).
What's left:

- Stamp a watermark or overlay/underlay onto pages of an *imported* PDF.
- N-up: place 2 / 4 / 8 source pages per sheet (booklet imposition, proof
  sheets).
- Page resize / crop / rotate on import.

### Split / extract — **S**

`Pdf::split('in.pdf')` → per-page files; `extract(pages: [2,5,7])`. Mostly
importer plumbing.

---

## Images & color

### CMYK & spot color — **M**

`Color::cmyk()` and `Color::spot(name, tint)` (Separation color space).
`ContentStream` color ops currently emit `g` / `rg` only — add `k` and
`/SepName cs`. Prerequisite for PDF/X.

### ICC color management — **M**

`/ICCBased` color spaces, embed input profiles on images, `/OutputIntent`.
Pairs with PDF/A / PDF/X.

### More image formats — **S–M**

- TIFF (multipage, LZW / PackBits / G4 fax).
- BMP.
- AVIF / JPEG 2000 (`/JPXDecode` — some viewer support).
- SVG → rasterise, or better, → native vector via the drawing primitives (**L**,
  needs an SVG path parser).

### Image reuse & downsampling — **S**

Deduplicate identical images (hash the decoded bytes) so a logo in a repeating
header is embedded once. Optional DPI ceiling that downsamples on the way in.

---

## Layout engine

### Context-aware components — **M**

`Component` expands once, statically. A `PageAware` variant —
`body(PageContext $ctx): BlockNode|iterable` expanded per physical sheet by the
`Paginator`, the same path headers/footers use — would unlock "Page X of Y"
*inside body flow*, running headers pulled from the last heading on the page,
and a generated table of contents. Bigger than plain `Component` because it
interacts with the two-pass page-count model.

### Absolutely / relatively positioned blocks in flow — **M**

`position: absolute | relative` on a block within normal flow (float out, offset
from normal position). Currently absolute placement is only via the separate
`$page->place()` area API.

### Floats & text wrap around images — **L**

`float: left | right` with subsequent text flowing around the float's box. The
line breaker would need per-line available-width that varies with y. Genuinely
new.

### CSS-grid-style / flex regions — **L**

Beyond `Columns`: a real 2-D region layout (named areas, spanning). Big.

### Bleed / trim / crop boxes — **S**

`/BleedBox` / `/TrimBox` / `/ArtBox` on the page dict, plus crop-mark
generation. Needed for professional print. Small on its own.

### Widow/orphan & keep across nested structures — **M**

The constraint pass exists for paragraphs; extend keep-together / keep-with-next
to work reliably through arbitrarily nested containers, lists and table row
groups (the plan flags "keep-together at scale").

---

## Tooling & developer experience

### Stylesheet class selectors — **done**

`StylePatch(class: 'lead callout')` on any block, `(new Stylesheet())->class('lead',
$patch)` for the rule. `StyleResolver::selectorsFor()` appends a block's classes
after its type selector, so a class rule beats the type rule, a later class beats
an earlier one, and the node's own patch still wins over all of them. Class-rule
keys are namespaced (`.lead`) so they can't collide with node-type selectors.
Block-level only — `resolveInline()` does not consult the stylesheet.

### Markdown → document — **M**

`Pdf\Markdown::toDocument()` — a CommonMark front-end producing the node tree
(headings, paragraphs, lists, tables, code blocks, blockquotes, images, links).
The inline-HTML helper is a partial precedent.

### Templating / data binding — **M**

A `Template` that takes a document tree with placeholders + a data array /
collection and produces N documents (mail merge). Or a Twig/Blade bridge.

### Streaming / incremental output — **L**

Emit the PDF page-by-page to a stream instead of buffering the whole document —
matters for 10,000-page batch jobs. Conflicts somewhat with the two-pass
"layout everything, then render" design; needs a bounded look-ahead mode.

### Diagnostics — **S**

- A "layout overlay" debug mode drawing box edges, baselines, margins, break
  points.
- `qpdf --check` style structural self-validation.
- Warnings surfaced as a collectable list rather than exceptions (overflow,
  clamped table, missing glyph).

### Performance — **M**

- Cache `FontMetrics::stringWidth` per (font, size, string) run.
- Reuse measured boxes across the two pagination passes instead of remeasuring.
- Profile the line breaker on large documents; the item model added allocations.

---

## Suggested near-term order

1. **Gradients + clipping paths** (M) — the rest of the drawing story now that
   solid-paint `Path` has shipped.
2. **Charts / sparklines** (M) — a thin layer on the path API.
3. **Document / field JavaScript** (M) — opt-in layer on top of the now-shipped
   AcroForm fields for Acrobat workflows, with the viewer-support caveat
   documented.
4. **Font subsetting** (L) — shrinks every embedded-font document (TrueType and
   CFF both embed whole today).
5. **XMP + tagged PDF + PDF/A** (L–XL) — the compliance track, if the audience
   needs it.
