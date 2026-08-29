# Plan — fonts & measurement

> **Status: shipped.** All four items landed (`FontFace` / numeric weights,
> `PageBuilder::textWidth()` / `measureBlocks()` / `TextMeasurer`,
> `ShrinkMode::FontSize`, OpenType/CFF embedding). This page stays as a design
> record; open follow-ups (CFF subsetting, synthetic bold/oblique, table-cell
> patch scaling under `ShrinkMode::FontSize`) are in [`../roadmap.md`](../roadmap.md).

Implementation plan for the four gaps surfaced rebuilding `examples/detail-sheet.php`
against Schier's `DetailBuilderService.ts`.

| # | Feature | Effort | Delivers |
|---|---|---|---|
| 1 | Public measurement helpers | M | `textWidth()` / `measureBlocks()` for absolute layout; unblocks #2 |
| 2 | `place()` shrink-to-fit by font size | M | legends / notes that reduce point size and re-wrap, not squash |
| 3 | Named / numeric font weights | M | Semibold, Light, Black as first-class cuts |
| 4 | OpenType / CFF (`.otf`) embedding | L | real brand fonts (Proxima Nova &c.) |

**Recommended order: 1 → 2 → 3 → 4.** The measurement track (1–2) is small, safe,
and immediately useful; the font track (3–4) is invasive and higher-value but
wants the weight model (3) settled before CFF (4) lands. Four PRs, ~4–6 focused
days. Every PR: PHPStan level 6 clean, golden bytes stable for all existing
documents, new unit + functional tests.

---

## 1 — Public measurement helpers · **M**

### Problem

`FontMetrics::stringWidth()` and `StackBox::contentHeightPt()` are internal.
Absolute-layout code can't measure a string to right-align it, or a block to
size its `place()` rectangle. The original detail builder does both constantly
(`x = titleX + widthOf("DETAIL" + "OO")`, widest-term column, legend pre-measure).

### API

On `PageBuilder`, results in the page's `units()`:

```php
public function textWidth(string $text, StylePatch $patch = new StylePatch()): float;
public function measureBlocks(iterable $blocks, float $width): float;   // → natural stacked height
```

Plus a standalone `Pdf\Text\TextMeasurer` (family/face/size → width) for use
outside the builder.

### The blocker — `PageBuilder` has no renderer

`page()` closures run in `DocumentBuilder::page()`, *before* `using()` attaches
the `DocumentRenderer` that owns the `FontRepository`. Three options:

| | approach | verdict |
|---|---|---|
| a | `page()` injects a `Measurer` — needs `using()` called first | ordering trap |
| b | **`DocumentBuilder` stores page closures, runs them in `build()`/`toString()`** once the renderer is known | **chosen** — nothing observable changes for existing code |
| c | `PageBuilder` lazily builds a *default* `Measurer` | wrong when the user registered fonts |

**(b):** `DocumentBuilder` keeps `list<callable>` configurators; a private
`resolvePages()` (called by `build()` and `toString()`) instantiates each
`PageBuilder` with a `Measurer` derived from `$this->renderer ?? DocumentRenderer::default()`.
`addPage(Page)` for pre-built pages is unaffected.

### Implementation

- `DocumentRenderer` exposes its `FontRepository` (or a factory for a `Measurer`).
- `PageBuilder` gains an optional `?Measurer $measurer` ctor arg + the two methods:

  ```php
  $style   = $this->documentBaseStyle();            // + stylesheet rule for the type
  $resolved = $patch->applyTo($style);
  $font    = $measurer->fonts()->use($resolved->fontFamily, $resolved->fontFace);
  $encoded = Encoding::forFont($text, $font->definition->encoding);   // match the line breaker
  return $this->unit->fromPoints($font->metrics->stringWidth($encoded, $resolved->fontSizePt));
  ```

  `measureBlocks()` → `$measurer->measureStack($blocks, $this->unit->toPoints($width), $style)->contentHeightPt()`, converted back.

- `Unit::fromPoints()` already exists.

### Caveats
- `textWidth` measures one unbroken run — no wrapping, no `\n`.
- Measurement honours stylesheet rules for named node types and inline patches;
  the parent is the document base style.

### Tests
- `textWidth('DETAIL', bold 10pt)` within ε of a value hand-computed from `helvetica.json`.
- `measureBlocks([Paragraph($long)], 200)` ≈ lineCount × lineHeight.
- pt vs mm page units.
- a `using()` renderer with a registered custom font measures *that* font.

---

## 2 — `place()` shrink-to-fit by font size · **M**

### Current behaviour

`DocumentRenderer::renderBlockArea()` wraps over-tall placed content in
`q <s> 0 0 <s> … cm … Q` — geometric scale. Text strokes thin out and the
column under-fills its width. Fine for a drawing; wrong for a text legend.

### Desired

Opt-in mode that lowers the effective font size (so lines re-wrap and re-flow)
until the stack fits — the original's `0.9×` loop.

### Design

```php
enum ShrinkMode { case Scale;  case FontSize;  case None; }

$p->place($x, $y, $w, $h, $blocks, $align, shrink: ShrinkMode::FontSize);
```

Carried on `Node\Placement` → `Placement\Blocks`.

**Runs in `Paginator` where `PlacedArea::forBlocks` is built** (it needs
re-measurement — the Measurer's job — and must emit a normal `StackBox` the
renderer draws at scale 1):

```
target = rectHeightPt ; width = rectWidthPt
lo, hi = 0.5, 1.0
stack  = measureStack(blocks, width, base)
if stack.contentHeightPt() <= target: done (factor 1.0)
repeat 6×:                              # binary search, ~0.8% precision
    mid = (lo + hi) / 2
    stack = measureStack(blocks, width, base.withFontScale(mid))
    stack.contentHeightPt() <= target ? lo = mid : hi = mid
use factor = lo
if still overflowing at 0.5 → fall back to ShrinkMode::Scale for the remainder
```

### `StyleResolver` change — a font scale that catches fixed sizes too

The original scales *everything*, including hard-coded sizes. So the scale can't
just be the parent style's `fontSizePt` (inline `StylePatch(fontSizePt: 9)`
would ignore it). Add:

```php
// StyleResolver
public function withFontScale(float $scale): self;   // clone; immutable elsewhere
// multiply into fontSizePt in resolveBlock() / resolveInline() / headingDefaults()
```

`Measurer::measureStack()` takes an optional scale (or a pre-scaled resolver
clone). `StyleResolver` is built once per render in `DocumentRenderer::render()`,
so the scaled variant must be scoped — clone, don't mutate.

### Tests
- block 2× too tall, `FontSize` mode → rendered size ≈ 0.7×, content stream has
  **no** `cm` scale wrap (scale ≈ 1).
- an inline `StylePatch(fontSizePt: 9)` inside also shrinks.
- 0.5 floor respected; falls back to `Scale` past it.
- `Scale` / `None` unchanged — `examples/sheet.php` golden byte-stable.

---

## 3 — Named / numeric font weights · **M**

### Current

`FontStyle` = `{Regular, Bold, Italic, BoldItalic}`. Keyed on by `Style::fontStyle`,
`StylePatch::{bold,italic}` → `FontStyle::of()`, `StyleResolver`, `FontRepository`
(`resolve` / `key` / `corePath`), `FontRegistry::use()`, `FontStyle::fileSuffix()`,
`FontWriter` (subset prefix).

### Target

Arbitrary weights 100–900 × roman/italic; a family registers a definition per
cut. The 4 core PDF fonts keep working byte-identically.

### Design

**New value object** `Pdf\Font\FontFace`:

```php
final readonly class FontFace {
    public function __construct(public int $weight = 400, public bool $italic = false) {}
    public function isBold(): bool { return $this->weight >= 600; }
    public static function fromLegacy(FontStyle $s): self;    // Bold → 700, …
}
```

- `Style::fontStyle` → `Style::fontFace: FontFace`. Ripples through
  `StyleResolver`, `Measurer`, `LineBreaker` run styles.
- `StylePatch` gains `?int $weight` beside `?bool $bold` (bold = weight-700 shorthand).
- `FontStyle` enum stays as a convenience (`->face()`), so `->withBold()` and
  the tutorials keep compiling.

**`FontRepository`:**
- `register(string $family, FontFace $face, string $path)`, key `family:weight:italic`.
- `resolve()` **fallback ladder** when the exact cut is missing:
  same-italic nearest weight → opposite italic, same weight → core alias.
- Core families expose only 400/700 × roman/italic; a request for 600 snaps to
  the nearest (700).

**Deferred to a follow-up (keeps this at M):** synthetic bold (text render mode
2 + small line width) and synthetic oblique (text-matrix shear), FPDF-style.

**`FontWriter`:** unchanged — `/BaseFont` already uses the definition's real
PostScript name.

### Migration
- `StylePatch(bold: true)` ≡ `StylePatch(weight: 700)` — unchanged output.
- `FontRepository::register('ProximaNova', new FontFace(600), 'proxima-semibold.json')` — new.
- Golden files: core + CevicheOne (TrueType) documents resolve identically →
  **no regeneration expected**; that is the acceptance test.

### Rollout
One mechanical commit (type change, everything still 400/700) + one behaviour
commit (the ladder). Golden byte-stability is the net.

### Tests
- `FontFace(600)` with 400/700 registered → 700; with 600 registered → 600.
- `StylePatch(bold:true)` == `StylePatch(weight:700)`.
- core `/BaseFont /Helvetica-Bold` byte-identical.
- legacy `FontStyle::Bold` path still resolves.

---

## 4 — OpenType / CFF (`.otf`) embedding · **L**

### Scope

Embed OpenType fonts with **PostScript / CFF outlines** (`sfnt` tag `OTTO`),
**simple** (≤ 256 glyphs, WinAnsi + Differences), **no subsetting** in v1 —
enough for Proxima Nova and most commercial `.otf`.

*(OpenType with TrueType outlines — tag `0x00010000` — already works through the
TrueType path; `makefont` accepts those `.otf` today. Only `OTTO` is the gap:
`ttfparser.php:67` errors on it.)*

### A · `tools/makefont/` — the definition

Replace the `OTTO` error with a CFF branch:

- sfnt table directory — already parsed.
- Metrics from the tables OTF shares with TTF: `head` (unitsPerEm, bbox),
  `hhea`/`hmtx` (advances), `cmap` fmt 4 (unicode → GID), `OS/2` (usWeightClass,
  capHeight, xHeight, fsSelection), `post` (italicAngle, underline). **No
  `glyf` / `loca`.**
- Extract the `CFF ` table blob verbatim → the font program (zlib-compress → `.z`).
- Definition JSON: `type: "Type1"`, new `"cff": true`, `file: "<name>.cff.z"`,
  `size1: <uncompressed CFF length>`, no `size2`, `originalsize`, standard `desc`
  with `Flags`.
- `cw[32..255]`: WinAnsi byte → unicode → GID (`cmap`) → advance (`hmtx`), ×1000/unitsPerEm.
- `diff` / `uv` (ToUnicode) exactly as the TrueType path builds them.

Needs a CFF **length read**, not a CFF parser — the table embeds whole.
~150–250 lines in `ttfparser.php` / `makefont.php`.

### B · `src/` — the writer

- `FontDefinition`: `public bool $isCff = false` (from `cff` in JSON); `FontLoader` reads it.
- `FontWriter::embedFontFiles()`: for a CFF definition emit
  `<< /Length … /Filter /FlateDecode /Subtype /Type1C >>` — **`/Subtype` on the
  stream dict**, no `/Length1`.
- `FontWriter::writeEmbeddableFont()`: when `isCff` —
  font dict `/Subtype /Type1` (not `/TrueType`);
  descriptor references `/FontFile3 <obj> 0 R` (not `/FontFile2`).
  `/Widths`, `/FirstChar 32 /LastChar 255`, `/Encoding` — unchanged.

### Out of scope (v1)
- CFF **subsetting** (rebuild CharStrings INDEX, subset charset/subrs). Full
  file embeds — 40–80 KB per cut. Follow-up, **L–XL**.
- CID-keyed CFF / `/Type0` — error clearly.
- `GPOS` / `GSUB` layout — n/a for simple Latin.

### Fixture / golden
Proxima Nova is commercial — cannot ship. Add a permissively-licensed CFF `.otf`
fixture: **Source Serif** or **IBM Plex** (both SIL OFL, ship CFF `.otf`), or a
tiny purpose-built one. New golden `tests/golden/otf-embed.pdf`.

### Tests
- `makefont` on the fixture → JSON `type: Type1`, `cff: true`, 256 `cw`, `size1`.
- render → `/FontFile3` with `/Subtype /Type1C`, font dict `/Subtype /Type1`.
- `qpdf --check` clean; `pdftotext` extracts text (ToUnicode).
- golden byte-stable; a known string's width matches the `hmtx` expectation.

### Sequencing
After #3, so `register('ProximaNova', new FontFace(600, false), …)` is
expressible. If #3 slips, #1 can land with the 4-value enum and Semibold
registered as family `'ProximaNova Semibold'` (the original's own workaround) as
a stopgap.

---

## Definition of done (all four)

- `composer stan` clean at level 6; no new `@phpstan-ignore`.
- `composer test` green; **golden PDFs byte-identical** except the one new
  `otf-embed.pdf` (verified with `pdftotext -layout` + `qpdf --check`).
- New unit tests in `tests/Unit/`, functional in `tests/Functional/`.
- `examples/detail-sheet.php` finishes on real Proxima-substitute cuts with
  `ShrinkMode::FontSize` on the legend and measured (not approximated) offsets.
- `docs/reference.md` + `docs/cookbook.md` updated; `docs/roadmap.md` entries
  moved to "implemented".
