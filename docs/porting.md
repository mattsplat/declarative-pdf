# Porting from FPDF

The imperative cursor API is gone. You no longer call `AddPage()`, move a
cursor, and `Cell()` things into place — you describe the document and a layout
engine places it.

## Setup

```php
// FPDF
$pdf = new FPDF('P', 'mm', 'A4');
$pdf->SetMargins(20, 20, 20);
$pdf->AddPage();

// now
use Pdf\Document;
$doc = Document::create()->page(function ($p) {
    $p->size(\Pdf\Geometry\PageSize::a4())->margin(20);
    // ... content ...
});
```

## Text

| FPDF | now |
|---|---|
| `SetFont('Arial','B',16); Cell(0,10,'Hi',0,1)` | `$p->heading(2, 'Hi')` or `$p->paragraph('Hi', new StylePatch(bold: true, fontSizePt: 16))` |
| `MultiCell(0, 5, $longText)` | `$p->paragraph($longText)` — wraps and paginates automatically |
| `Write(5, $text)` | `$p->paragraph($text)` |
| `Ln(10)` | `$p->spacer(10)` (or block spacing via style `spaceAfterPt`) |
| manual `$pdf->SetTextColor(...)` before/after | `new StylePatch(color: Color::rgb(...))` on the block or run |
| bold/italic mid-sentence | `InlineSequence::of('a ')->withBold('b')->withItalic('c')` |
| underline via `SetFont('','U')` | `->withUnderline('text')` |
| `WriteHTML()` add-on (tuto6) | `$p->html('<b>..</b> <a href="..">..</a>')` (inline tags only) |
| `AddFont(...)` + `SetFont('Custom',...)` (tuto7) | `FontRepository::register('Custom', FontStyle::Regular, 'Custom.json')`, then `new StylePatch(fontFamily: 'Custom')` |
| `{nb}` page-count alias | `$p->footer(fn ($ctx) => new Paragraph("Page {$ctx->pageNumber} of {$ctx->pageCount}"))` |
| non-ASCII: `iconv`, `$isUTF8` flags | pass UTF-8; it is transcoded to the font encoding for you |

## Structure

| FPDF | now |
|---|---|
| `AddPage()` mid-flow | `$p->pageBreak()` |
| subclass + override `Header()` / `Footer()` | `$p->header(fn ($ctx) => ...)` / `$p->footer(...)` returning block nodes |
| override `AcceptPageBreak()` for columns (tuto4) | `$p->columns([...], count: 2)` |
| `Rect()` / `SetFillColor()` around a `Cell` to make a panel | `$p->container([...], new StylePatch(padding: ..., border: ..., background: ...))` |
| bullet list built by hand | `$p->bulletList([...])` / `$p->orderedList([...])` |

## Tables (tuto5)

FPDF: compute column widths yourself, loop `Cell()`, redraw the header after
every `AddPage()`.

```php
$p->table([
    new TableRow(['Country', 'Capital', 'Area']),
    new TableRow(['Austria', 'Vienna', '83859']),
    // ...
], headerRows: 1);
```

Column widths are computed from content. Set them explicitly with
`ColumnWidth::fixed(90)`, `ColumnWidth::fraction(2)` or
`ColumnWidth::auto(minPt: 40, maxPt: 120)`. Header rows repeat on every page.

## Images

| FPDF | now |
|---|---|
| `Image('logo.png', 10, 8, 30)` (absolute) | `$p->image('logo.png', width: 30)` (flows in the block stack) |
| absolute placement on a big sheet | `$p->units(Unit::In)->placeImage(1, 1, 26, 21, 'plan.png', Fit::Contain)` |
| WebP with transparency was flattened to black | preserved (routed through PNG) |

## Importing / merging PDFs

FPDF cannot read PDFs. There is now a pure-PHP importer for **trusted,
unencrypted** sources (FPDI-style — one page becomes a vector Form XObject):

```php
// stamp / assemble: place page 1 of an external PDF into an area
$p->units(Unit::Mm)->placePdf(15, 15, 260, 180, 'drawing.pdf', page: 1, fit: Fit::Contain);

// or drive the reader directly
$doc = Pdf\Import\PdfImportDocument::fromFile('report.pdf');
$doc->pageCount();
$page = $doc->page(2);   // MediaBox/CropBox, rotation, content, dependencies
```

Full document-to-document merge (bookmarks, links, forms) is not built in —
shell out to `qpdf --empty --pages a.pdf b.pdf -- out.pdf` for that.

## Links

```php
// external
InlineSequence::of('See ')->withLink('the site', 'https://example.com');

// internal: put an anchor, link to #name
$p->anchor('chapter-2');
// ... elsewhere ...
InlineSequence::of('Jump to ')->withLink('chapter 2', '#chapter-2');
```

`AddLink()` / `SetLink()` are gone — anchor positions resolve after layout.

## Output

| FPDF | now |
|---|---|
| `$pdf->Output('F', 'out.pdf')` | `$doc->save('out.pdf')` |
| `$pdf->Output('S')` | `$doc->toString()` |
| `$pdf->Output('I')` / `'D'` | `$doc->output()->inline()` / `->download()` |
