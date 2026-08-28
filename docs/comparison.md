# declarative-pdf vs. FPDF, TCPDF, tc-lib-pdf, PDFBlocks

An honest read on where this library sits. Short version: it competes on the
**layout engine**, not on PDF-feature coverage — and for anything that needs
signatures, forms, encryption, CMYK or CJK, the others win today.

The PHP libraries (FPDF / TCPDF / tc-lib-pdf) are the direct lineage.
[PDFBlocks](#pdfblocks-swift) is covered separately at the end — it's the same
*idea* (declarative tree → layout engine → PDF) in a different ecosystem.

## What each one is

| | Model | Size | Deps | Licence | Age |
|---|---|---|---|---|---|
| **FPDF 1.9** | imperative cursor (`AddPage`/`SetFont`/`Cell`/`MultiCell`/`Ln`); you do the layout math | one ~2k-line untyped class | 0 | permissive | ~20 yr |
| **TCPDF** | imperative + an HTML/CSS renderer (`writeHTML`) | one ~27k-line class | 0 | LGPL-2.1 | ~15 yr |
| **tc-lib-pdf** | imperative drawing API (`getPage()`, `addTextCell()`); you compute positions | ~15 composer packages | several | LGPL-3 | ~8 yr |
| **declarative-pdf** | immutable node tree → `measure → paginate → render → serialise`; the engine places everything | ~135 files, one package | 0 (`zlib` + `mbstring`) | MIT | months |

## Feature matrix

| | FPDF | TCPDF | tc-lib-pdf | declarative-pdf |
|---|---|---|---|---|
| Text wrap / flow across styles | one font per `MultiCell` | `writeHTML` | manual | **engine** |
| Pagination: keep-together, widow/orphan, keep-with-next | manual | partial | manual | **engine** |
| Auto table column sizing | hand-coded widths | rudimentary | manual | **engine** (CSS-style) |
| Table row split across pages, repeating header | manual | partial | manual | **engine** |
| Headers / footers | subclass | subclass / methods | manual | closures + real page count |
| Multi-column flow | `AcceptPageBreak` hack | columns | manual | `Columns` block |
| Core-14 fonts | ✓ | ✓ | ✓ | ✓ |
| Embed TrueType | via `makefont` | ✓ | ✓ (loads `.ttf` directly) | via `makefont` |
| Font subsetting | ✗ | ✓ | ✓ | ✗ (whole `glyf`) |
| OTF / CFF, CID, CJK, RTL / bidi | ✗ | ✓ | ✓ | ✗ *(OTF in progress)* |
| Numeric font weights | ✗ | ✗ | ✓ | ✗ *(in progress)* |
| Vector drawing (paths / béziers / arcs) | lines + rects | ✓ | ✓ | ✗ |
| CMYK / spot / ICC colour | ✗ | ✓ | ✓ | ✗ (RGB + gray) |
| Images (JPEG/PNG/GIF/WebP), soft masks | JPEG/PNG/GIF | ✓ | ✓ | ✓ (+ URL / `data:` sources) |
| Internal + external links | manual | ✓ | ✓ | ✓ |
| Bookmarks / outlines | ✓ | ✓ | ✓ | ✗ |
| AcroForms / JavaScript | ✗ | ✓ | partial | ✗ |
| Digital signatures | ✗ | ✓ | ✓ | ✗ |
| Encryption (RC4 / AES) | ✗ | ✓ | ✓ | ✗ |
| Barcodes (1D / 2D) | script | ✓ | ✓ | ✗ |
| SVG | ✗ | ✓ | partial | ✗ |
| Tagged PDF / PDF-A / PDF-UA | ✗ | ✓ | partial | ✗ |
| PDF import / merge | FPDI (separate) | ✓ | ✗ | 1 page → vector Form XObject |
| UTF-8 input | ✗ (tFPDF fork) | ✓ | ✓ | ✓ |
| Deterministic / golden-testable output | incidental | ✗ | ✗ | **design goal** |
| Static types / analysis | ✗ | ✗ | partial | PHPStan level 6 |
| Production mileage | vast | vast | moderate | none yet |

## Where declarative-pdf genuinely wins

- **The layout engine.** Nobody in the FPDF/TCPDF lineage has a designed box
  model with `split()`-based pagination, widow/orphan control, `keepWithNext`,
  and table rows that break across pages re-emitting their header. `writeHTML`
  is the nearest thing and it is an unpredictable HTML renderer, not an engine
  you can reason about. For invoices, reports, contracts, long tables — this is
  a category better.
- **Immutable typed tree.** Build it in one place, render in another, snapshot
  it, diff it, unit-test layout logic without emitting a byte.
- **Determinism.** With a fixed clock and `compress: false` the output is
  byte-stable, so golden-file tests actually catch regressions. No other library
  here treats this as a requirement.
- **MIT** vs. LGPL for TCPDF / tc-lib.
- **Zero runtime deps** — matches FPDF, avoids tc-lib-pdf's package sprawl.

## Where it loses

- **Feature breadth is a chasm.** No forms, JS, signatures, encryption,
  bookmarks, barcodes, CMYK, vector drawing, tagged PDF, subsetting, CJK/RTL.
  TCPDF has all of it today.
- **It inherited FPDF's font model** — WinAnsi, 256 glyphs, the offline
  `makefont` `.json` step. That model is exactly what blocks OTF, subsetting,
  CID and proper Unicode. tc-lib-pdf-font subsets straight from a `.ttf` with no
  build step.
- **The declarative promise breaks for designed layouts.** `place()` / `frame()`
  with manual point coordinates is the same arithmetic you'd do in FPDF — see
  [`examples/detail-sheet.php`](../examples/detail-sheet.php). The engine's
  advantage is specific to *flowing* content.
- **No escape hatch.** FPDF lets you `_out()` raw operators; TCPDF exposes
  low-level draw calls. Here the pipeline is closed — if the engine can't
  express it, you wait for a library change.
- **No streaming.** Two-pass layout + whole-document buffering; large batch jobs
  will hurt.
- **New, one author, no ecosystem.** No FPDI equivalent, no barcode package, no
  community scripts. The ported writer is FPDF-proven at the byte level;
  everything above it is not.

## Syntax — a flowing report with a repeating-header table

```php
use Pdf\Document;
use Pdf\Layout\PageContext;
use Pdf\Node\{Paragraph, TableCell, TableRow};
use Pdf\Style\{StylePatch, TextAlign};

$rows = [new TableRow([new TableCell('Region'), new TableCell('Revenue')])];
foreach (['North' => '4.2', 'South' => '3.1', 'West' => '5.8'] as $region => $rev) {
    $rows[] = new TableRow([
        new TableCell($region),
        new TableCell($rev, patch: new StylePatch(align: TextAlign::Right)),
    ]);
}

Document::create()
    ->meta(fn ($m) => $m->title('Q3 Results'))
    ->page(function ($p) use ($rows) {
        $p->header(fn (PageContext $c) => new Paragraph(
            "Q3 Results — page {$c->pageNumber}/{$c->pageCount}",
            new StylePatch(fontSizePt: 9),
        ));
        $p->heading(1, 'Regional breakdown');
        $p->paragraph('The table is column-sized automatically and its header row '
            . 'repeats on every page it spills onto.');
        $p->table($rows, headerRows: 1);
    })
    ->save('q3.pdf');
```

The FPDF equivalent is ~50 lines: a subclass for the header, hand-computed
column widths, manual `MultiCell`/`Ln`, and an `AcceptPageBreak` override to
repeat the header. The TCPDF equivalent is a `writeHTML('<h1>…</h1><table>…')`
string — concise, but the pagination and column widths are whatever the HTML
engine decides.

For a **fixed layout** the picture flips: `place()` / `frame()` with manual
coordinates is no more concise than FPDF's `Rect()` / `SetXY()` / `Cell()`.

## Which to use

| Need | Reach for |
|---|---|
| Flowing multi-page reports, invoices, contracts, long tables | **declarative-pdf** |
| Reproducible / golden-tested output, a typed codebase | **declarative-pdf** |
| Signatures, encryption, AcroForms, barcodes, PDF/A, CJK/RTL | **TCPDF** (or **tc-lib-pdf** if you want it modular and can take LGPL-3) |
| A tiny dependency for simple documents, or an existing FPDF codebase | **FPDF** |
| Merge / stamp / import existing PDFs at scale | FPDI + FPDF, or shell out to `qpdf` |
| Pixel-exact designed one-pagers (cutsheets, labels) | any of them — it's manual coordinates regardless |
| SwiftUI-style DSL, on-device on macOS / iOS, with gradients + vector graphics | **[PDFBlocks](#pdfblocks-swift)** (Swift; not for servers) |

## PDFBlocks (Swift) {#pdfblocks-swift}

[dkyowell/PDFBlocks](https://github.com/dkyowell/PDFBlocks) (MIT, v0.2.4 beta) is
the closest thing to a sibling: a **SwiftUI-inspired declarative layout engine
for PDF**. Same core idea as declarative-pdf — you declare a tree of blocks and
the engine handles positioning and pagination — but a very different set of
trade-offs because of what it's built on.

### The architectural fork

| | PDFBlocks | declarative-pdf |
|---|---|---|
| Renders via | Apple **CoreGraphics** PDF context + **CoreText** | a hand-written PDF byte writer (ported from FPDF) |
| Runs on | macOS / iOS only | any host with PHP 8.3 (Linux servers, CI, containers) |
| Gets "for free" from the platform | text shaping, kerning, ligatures, font embedding + subsetting, vector paths, gradients, rotation, opacity, blend | nothing — every one is hand-built or absent |
| Byte-deterministic output | no (CG stamps time / version) | yes (a design goal; golden-file tested) |
| Automated tests | none yet — "Unit tests" is a *long-term* roadmap goal | 185 tests + golden PDFs |

PDFBlocks trades **portability and reproducibility** for **typography and
drawing polish**; declarative-pdf trades the reverse.

### Syntax

PDFBlocks decorates **nodes with chained modifiers** (`Text("x").bold().padding(8)`)
assembled by Swift **result builders**; declarative-pdf passes a **`StylePatch`
value object** to a node and builds children as **PHP arrays / closures**.
PDFBlocks reads like SwiftUI; declarative-pdf reads like a builder API — and its
`Table` is hand-assembled per cell where PDFBlocks' takes type-safe `KeyPath`
columns with automatic data grouping.

A full construct-by-construct side-by-side — hello world, styled/inline text,
page setup, stacks, headers, columns, images, the `Table`, reusable components,
vector drawing — is in
[`pdfblocks-vs-declarative.md`](pdfblocks-vs-declarative.md).

### Feature deltas

**PDFBlocks has, declarative-pdf doesn't:** vector shapes (Capsule / Circle /
Ellipse / Rectangle / RoundedRectangle / Line / Shape), linear + radial
gradients, `rotationEffect` / `scaleEffect` / `opacity` / `offset` on any block,
text stroke/fill, real kerning, `ZStack` / `VGrid`, `.environment`-style value
propagation, and instant Xcode-Preview rendering while you type.

**declarative-pdf has, PDFBlocks doesn't (PDFBlocks roadmap items marked ⏳):**
internal + external links ⏳, clickable regions ⏳, justified text ⏳,
single-page PDF import, images from URL / `data:` URIs, byte-deterministic
output, and a real test suite.

**Neither has:** AcroForms, JavaScript, encryption, digital signatures,
bookmarks/outlines, tagged PDF/PDF-A. Both explicitly punt **barcodes and
charts** to "render it as an image."

### When PDFBlocks is the right call

Apple-platform app that generates reports/invoices on-device or on a Mac, wants
SwiftUI-grade ergonomics, and needs gradients / vector graphics / good
typography. It cannot run on a Linux server, and its output isn't reproducible —
if either matters, it's out.

## Roadmap context

The gaps most often hit — OTF/CFF fonts, numeric weights, vector drawing,
bookmarks, `place()` shrink-to-fit, public text measurement — are tracked in
[`roadmap.md`](roadmap.md), with a worked plan for the font + measurement
cluster in [`plans/fonts-and-measurement.md`](plans/fonts-and-measurement.md).
