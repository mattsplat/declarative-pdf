# Architecture

The library turns an immutable **document tree** into PDF bytes through a fixed
pipeline. Everything below the tree works in PostScript points with a top-left
origin; the Y axis is flipped in exactly one place.

```
Node\Document                     immutable value-object tree
        │  Builder\* (optional fluent construction)
        ▼
Style\StyleResolver               per-node Style: inheritance + defaults + stylesheet + patch
        ▼
Layout\Measurer                   node -> Box tree
   │  Text\Encoding               UTF-8 -> font encoding (cp1252)
   │  Layout\LineBreaker          greedy wrap over an item stream
   │     (Layout\Inline\*: WordItem / SpaceItem / BreakItem / BoxItem)
   │  Layout\TableLayout          automatic column sizing
        ▼
Layout\Paginator                  Box tree -> list<PhysicalPage>
   │  Box::split(availableHeight)  orphan/widow, keepWithNext, keepTogether
   │  headers/footers measured once, rendered per page with a PageContext
   │  absolute Placement areas resolved onto the first sheet
        ▼
Render\DocumentRenderer           two passes:
   1. render each page's ContentStream (a Canvas), collecting links + anchors
   2. serialise: page objects, annotations, pages tree, fonts, images,
      resource dict, info, catalog, xref, trailer
        ▼
Render\PdfWriter                  append-only byte buffer (ported from _put*/_enddoc)
```

## The box model (`Layout\Box`)

Every measured unit implements `Box`:

| method | meaning |
|---|---|
| `contentHeightPt()` | height excluding the box's own vertical margins |
| `marginBeforePt()` / `marginAfterPt()` | collapsing margins (a `StackBox` collapses them) |
| `keepWithNext()` / `keepTogether()` | pagination hints from the resolved style |
| `split(availableHeightPt)` | `[head, tail]`; `head === null` means "move the whole box down"; `tail === null` means "it fit" |
| `render(Canvas, x, yTop, width)` | draw at an absolute position |
| `min/maxIntrinsicWidthPt()` | narrowest / natural width, for table column sizing |

Concrete boxes: `StackBox` (vertical stack + the core pagination loop),
`TextBox`, `ContainerBox` (padding / border / background), `ListItemBox`,
`ColumnsBox`, `TableBox` (+ `TableRowBox` / `TableCellBox`), `ImageBox`,
`SpacerBox`, `RuleBox`, `PageBreakBox`, `AnchorBox`.

`StackBox::split()` is the paginator's engine: it places children that fit,
splits the first that doesn't (unless `keepTogether`), and drags trailing
`keepWithNext` boxes onto the next page.

## The Canvas (`Layout\Canvas`)

Boxes draw through `Canvas`, implemented by `Render\ContentStream`. Only
`text()` and `fillRect()` are real primitives; `strokeEdges()`,
`horizontalLine()`, borders and underlines are built from `fillRect()`.
`image()` emits an XObject `Do`. `link()` and `anchor()` just record positions
that `DocumentRenderer` turns into annotations after layout.

## What was ported from FPDF vs. rewritten

(`fpdf.php:NNN` citations throughout the source refer to line numbers in the
FPDF 1.9 release; that file is not part of this repository.)

| Ported near-verbatim (locked by golden PDFs) | Rewritten |
|---|---|
| PDF object writer, xref, trailer (`_put*`, `_enddoc`) | style resolution |
| font metrics + embedding, ToUnicode CMap (`_putfonts`, `_tounicodecmap`) | the box model + `split()` pagination |
| image decoders (`_parsejpg/png/gif/webp`, `_putimage`) | table column autosizing |
| the `MultiCell` line-break scan loop | headers/footers as post-layout callables |
| link annotation writing (`_putlinks`) | multi-column layout |
| the Y-axis flip idiom (now centralised in `PageGeometry::flipY`) | text encoding (UTF-8 in, transcoded once) |
| the `MultiCell` greedy strategy | the line breaker's item model (words / spaces / breaks / inline boxes) |

## PDF import (`Pdf\Import\*`)

A small pure-PHP reader for trusted sources: `PdfParser` (recursive-descent
object grammar), `PdfReader` (classic xref tables + xref streams + object
streams + `/Prev` chains; rejects `/Encrypt`), `PdfImportDocument` (page-tree
walk with attribute inheritance, transitive resource collection).
`Render\FormXObjectWriter` re-emits a page as a Form XObject with its
dependency objects renumbered (`Render\PdfValueWriter` serialises values with a
ref map). `Render\ImportRegistry` interns and deduplicates placed pages.
`TextExtractor` runs the other direction: a small content-stream interpreter
(state in `TextExtractorState`) that turns an `ImportedPage` back into plain
text, decoding through `ToUnicodeCmapParser` or WinAnsi.

## Determinism

`DocumentRenderer` takes a `Support\Clock` so `/CreationDate` can be pinned, and
a fixed `producer` string. With compression off the output is byte-stable, which
the golden-file tests rely on (`tests/golden/*.pdf`, regenerate with
`UPDATE_GOLDENS=1`).
