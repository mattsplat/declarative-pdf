# PDFBlocks vs. this library — side by side

[PDFBlocks](https://github.com/dkyowell/PDFBlocks) (Swift, MIT, v0.2.4 beta) and
this library share one idea: **describe a document as a tree of blocks and let a
layout engine measure, paginate and place it.** Neither moves a cursor.

They differ in everything underneath:

| | PDFBlocks | declarative-pdf |
|---|---|---|
| Language | Swift 5.9+, macOS / iOS only | PHP 8.3+, any host |
| Renders through | Apple **CoreGraphics** PDF + **CoreText** | a hand-written PDF byte writer |
| DSL | SwiftUI result builders + chained modifiers | fluent builder + `StylePatch` value objects |
| Free from the platform | shaping, kerning, ligatures, font subsetting, vector paths, gradients, rotation, opacity | none — hand-built or absent |
| Deterministic bytes | no | yes (golden-tested) |

So PDFBlocks reads like SwiftUI and gets rich typography and drawing for free;
declarative-pdf reads like a builder API, runs anywhere, and its output is
reproducible. The rest of this page is the syntax, construct by construct.
Declarative snippets are trimmed forms of what runs in [`examples/`](../examples).

---

## 1. Hello world

**PDFBlocks**

```swift
import PDFBlocks

struct Doc: Block {
    var body: some Block {
        Text("Hello, world.").padding(.in(1))
    }
}

let data = Doc().render()          // Data — display, save, upload
```

**Declarative**

```php
use Pdf\Document;

Document::create()
    ->page(fn ($p) => $p->paragraph('Hello, world.'))
    ->save('out.pdf');             // or ->toString() for the bytes
```

PDFBlocks builds a `Block` value type and calls `.render()`. This library builds
the same kind of immutable tree behind `Document::create()` and renders on
`save()` / `toString()`.

---

## 2. Text — styling and mixed runs

**PDFBlocks** — one modifier per property, chained; concatenate `Text` for a
mixed line:

```swift
Text("Warning")
    .font(.system(size: 14))
    .bold()
    .foregroundColor(.red)
    .padding(.bottom, 12)

HStack(spacing: 0) {
    Text("Plain ")
    Text("bold").bold()
    Text(" tail")
}
```

**Declarative** — one `StylePatch` bag per node; an `InlineSequence` for a mixed
line:

```php
use Pdf\Node\Paragraph;
use Pdf\Style\StylePatch;
use Pdf\Color\Color;
use Pdf\Text\InlineSequence;

new Paragraph('Warning', new StylePatch(
    bold: true,
    fontSizePt: 14,
    color: Color::rgb(200, 0, 0),
    spaceAfterPt: 12,
));

new Paragraph(
    InlineSequence::of('Plain ')->withBold('bold')->withRun(' tail'),
);
```

`InlineSequence` also has `withItalic` / `withUnderline` / `withLink` /
`withSuperscript` / `withImage` / `withBreak`, or take a subset of HTML with
`Html::toInline('Plain <b>bold</b> tail')`.

---

## 3. Page setup

**PDFBlocks** — `Page` blocks; sizes may differ within one document:

```swift
struct Doc: Block {
    var body: some Block {
        Page(size: .letter, margins: .in(1)) { … }
        Page(size: .a4, margins: .mm(24)) { … }
    }
}
```

**Declarative** — configure the `PageBuilder`; one master per `page()` call:

```php
$p->size(PageSize::letter())->margin(1, Unit::In);
// or landscape with per-edge margins in points:
$p->size(PageSize::a4())->landscape()->margins(new Edges(24, 24, 24, 24));
```

Both accept `pt` / `in` / `mm`. PDFBlocks: `.in(1)` / `.mm(24)` / `.pt(72)`
anywhere a length is taken. This library: a `Unit` at the API boundary, then
everything is points internally.

---

## 4. Stacks, nesting, cascading style

**PDFBlocks** — `VStack` / `HStack` / `ZStack`, `spacing: .flex` for even
distribution, and modifiers on a container **cascade to its children**:

```swift
VStack(spacing: .flex) {
    Text("Title").bold()
    HStack(spacing: .flex) { Text("left"); Text("right") }
    Columns(count: 2, spacing: 36) {
        Text(longText).truncationMode(.wrap)
    }
}
.italic()
.fontDesign(.serif)
.border(Color.black, width: 8)
```

**Declarative** — `Container` (a padded / bordered / backed box) and `Columns`;
inherited style comes from the document base style or a `Stylesheet`, not from
wrapping:

```php
use Pdf\Node\{Container, Columns, Paragraph};

new Container([
    new Paragraph('Title', new StylePatch(bold: true)),
    // no HStack — a one-row Table or place() gives side-by-side
    new Columns([new Paragraph($longText)], count: 2, gutterPt: 36),
], new StylePatch(
    italic: true,
    border: Border::uniform(8),
    paddingPt: Edges::all(6),
));
```

Two gaps to note: this library has **no `HStack`** (use a 1-row borderless
`Table`, or absolute `place()`), and **no `ZStack`** (use `place()` /
`frame()` overlays). PDFBlocks has both, plus `VGrid`.

---

## 5. Header, footer, page numbers

**PDFBlocks** — structural: wrap the flow block in a `VStack`; anything
surrounding the first "wrap" block repeats on every page. `PageNumberReader`
supplies the number (and, opt-in at ~2× cost, the total):

```swift
VStack {
    PageNumberReader(computePageCount: true) { proxy in
        if proxy.pageNo > 0 {
            Text("Page \(proxy.pageNo) of \(proxy.pageCount)")
                .padding(.horizontal, .max)
        }
    }
    Columns(count: 2, spacing: 36, wrap: true) {   // the repeating flow
        Text(body).truncationMode(.wrap)
    }
    Text("Confidential").padding(.horizontal, .max) // repeats as a footer
}
```

**Declarative** — explicit `header()` / `footer()` callbacks; the real page
count is always available:

```php
$p->header(fn (PageContext $c) => $c->pageNumber > 1
    ? new Paragraph("Page {$c->pageNumber} of {$c->pageCount}",
        new StylePatch(align: TextAlign::Center))
    : []);                                          // nothing on page 1
$p->footer(fn (PageContext $c) => new Paragraph('Confidential',
    new StylePatch(align: TextAlign::Center)));
$p->columns([new Paragraph($body)], count: 2, gutterPt: 36);
```

PDFBlocks pays for the page count with a second layout pass and makes it
opt-in; this library always runs two passes, so `pageCount` is free at the call
site.

---

## 6. Multi-column flow

**PDFBlocks**

```swift
Columns(count: 3, spacing: 18, wrap: true) {
    Text(article).truncationMode(.wrap)
}
```

**Declarative**

```php
$p->columns([new Paragraph($article)], count: 3, gutterPt: 18);
```

Both fill column-then-column, then page-then-page on overflow.

---

## 7. Images

**PDFBlocks** — resizable, aspect-locked; SF Symbols too:

```swift
Image("logo").frame(width: .in(2))
Image(.init(systemName: "checkmark.seal.fill")).frame(width: 12)
```

**Declarative** — block or absolute; a path, URL, or `data:` URI:

```php
$p->image('logo.png', width: 50);                                  // mm, flows
$p->placeImage(0, 0, 144, 96, 'https://cdn…/logo.png', Fit::Contain); // absolute
```

PDFBlocks has no fetch layer (you hand it image data); this library will fetch
an `http(s)://` URL itself. Neither does SVG.

---

## 8. Tables — where they diverge most

**PDFBlocks** — `Table` is generic over the row type. Columns are declared with
type-safe `KeyPath`s and per-type formatters; **data grouping is automatic**,
and group headers / footers get the group's rows for computing summaries.
Verbatim from the repo's report example:

```swift
struct ExampleReport: Block {
    let data: [CustomerData]

    var body: some Block {
        Table(data) {
            TableColumn("Last Name", value: \.lastName, width: 20)
            TableColumn("First Name", value: \.firstName, width: 20)
            TableColumn("Address", value: \.address, width: 35)
            TableColumn("City", value: \.city, width: 25)
            TableColumn("State", value: \.state, width: 10)
            TableColumn("Zip", value: \.zip, width: 10)
            TableColumn("DOB", value: \.dob, format: .mmddyy, width: 10, alignment: .trailing)
        } groups: {
            TableGroup(on: \.state, order: <, spacing: .pt(12)) { _, value in
                Text(stateName(abberviation: value)).fontSize(12).bold()
                    .padding(.trailing, .max)
                TableColumnTitles()
            } footer: { rows, value in
                Divider(thickness: .pt(0.75), padding: .pt(2))
                Text("\(rows.count) records for \(stateName(abberviation: value))").bold()
                    .padding(.leading, .max)
            }
        } pageHeader: {
            PageNumberReader { proxy in
                HStack(spacing: .flex) { Text("Donor Report"); Text("Page \(proxy.pageNo)") }
                    .fontSize(12).bold().padding(.bottom, 12)
                if pageNo > 1 { TableColumnTitles() }
            }
        }
        .font(.system(size: 8))
    }
}
```

**Declarative** — you build every cell. Column widths are automatic (or
explicit via `ColumnWidth`); rows split across pages and the header rows repeat;
grouping and group summaries are your loop:

```php
use Pdf\Node\{Table, TableRow, TableCell, Paragraph, Rule};
use Pdf\Style\{StylePatch, TextAlign};

$rows = [new TableRow([
    new TableCell('Last Name'), new TableCell('First Name'),
    new TableCell('City'), new TableCell('State'),
    new TableCell('DOB', patch: new StylePatch(align: TextAlign::Right)),
])];

foreach (groupByState($data) as $state => $people) {
    $rows[] = new TableRow([new TableCell($state,
        colspan: 5, patch: new StylePatch(bold: true, fontSizePt: 12))]);

    foreach ($people as $c) {
        $rows[] = new TableRow([
            new TableCell($c->lastName),
            new TableCell($c->firstName),
            new TableCell($c->city),
            new TableCell($c->state),
            new TableCell($c->dob->format('m/d/y'),
                patch: new StylePatch(align: TextAlign::Right)),
        ]);
    }

    $rows[] = new TableRow([new TableCell(count($people) . " records for {$state}",
        colspan: 5, patch: new StylePatch(bold: true, align: TextAlign::Right))]);
}

$p->header(fn ($c) => new Paragraph("Donor Report — page {$c->pageNumber}",
    new StylePatch(bold: true, fontSizePt: 12, spaceAfterPt: 12)));
$p->table($rows, headerRows: 1);
```

PDFBlocks' `Table` is a class ahead for tabular reports — the grouping,
summaries and column titles are declared once and the engine drives them. This
library's `Table` is lower-level: more code, but explicit column-width control
(`ColumnWidth::auto()` / `fixed()` / `fraction()`), `colspan`, per-cell
backgrounds and vertical alignment, and the same cross-page row-split +
repeating-header behaviour.

---

## 9. Reusable components

**PDFBlocks** — a component is just another `Block`:

```swift
struct Callout: Block {
    let text: String
    var body: some Block {
        Text(text).padding(8).background(Color.yellow)
    }
}
// Callout(text: "Note")
```

**Declarative** — a function (or small class) returning nodes:

```php
function callout(string $text): Container {
    return new Container([new Paragraph($text)], new StylePatch(
        paddingPt: Edges::all(8),
        background: Color::rgb(255, 245, 150),
    ));
}
// $p->add(callout('Note'));
```

---

## 10. Vector drawing and gradients

**PDFBlocks only.** CoreGraphics gives it shapes, strokes, fills and gradients
as first-class blocks:

```swift
Rectangle()
    .fill(LinearGradient(colors: [.blue, .white], startPoint: .top, endPoint: .bottom))
    .overlay { Circle().stroke(Color.black, lineWidth: 2) }
    .frame(width: 200, height: 120)
    .rotationEffect(.degrees(-4))
```

This library has **no path / shape API and no gradients** — only `frame()`
(bordered/filled rectangles) and table/rule borders. Vector primitives are on
the [roadmap](roadmap.md).

---

## Concept mapping

| PDFBlocks | declarative-pdf |
|---|---|
| `struct X: Block { var body: some Block { … } }` | `Document::create()->page(fn ($p) => …)` or a `Node\*` tree |
| `.render() -> Data` | `->toString(): string` / `->save($path)` |
| `Text("…")` | `Paragraph` / `Heading` / `InlineSequence` |
| `Text(a) + Text(b).bold()` | `InlineSequence::of($a)->withBold($b)` |
| `.font()` / `.bold()` / `.fontSize()` / `.foregroundColor()` (chained) | one `StylePatch(bold: …, fontSizePt: …, color: …)` |
| modifier on a container cascades to children | document base style / `Stylesheet` rules |
| `VStack` | the default block flow / `Container` |
| `HStack` | — (1-row `Table`, or `place()`) |
| `ZStack` | — (`place()` / `frame()` overlays) |
| `Columns(count:wrap:)` | `Columns(count:gutterPt:)` |
| `VGrid` | — |
| `Page(size:margins:)` | `$p->size(…)->margins(…)` |
| `Spacer` / `Divider` | `Spacer` / `Rule` |
| `Image("…").frame(width:)` | `image($path, width:)` / `placeImage(x,y,w,h,…)` |
| `Table(data){ TableColumn(value:\.kp) } groups:{ TableGroup(on:\.kp) }` | `Table` of hand-built `TableRow` / `TableCell`; group yourself |
| `PageNumberReader { $0.pageNo }` | `PageContext->pageNumber` in a `header()` / `footer()` |
| header = surround the wrap block in a `VStack` | `$p->header(fn ($ctx) => …)` |
| `Rectangle()` / `Circle()` / `LinearGradient` | — |
| `.rotationEffect` / `.opacity` / `.scaleEffect` / `.offset` | — (baseline shift only) |
| `.environment(\.key, value)` | — |

## What each has that the other doesn't

**PDFBlocks:** `HStack` / `ZStack` / `VGrid`, vector shapes, linear + radial
gradients, per-block rotate / scale / opacity / offset, text stroke & fill,
real kerning, SF Symbols, `.environment` value propagation, and instant
Xcode-Preview rendering while you type. `Table` with `KeyPath` columns and
automatic grouping.

**declarative-pdf:** internal + external links, single-page PDF import, images
from `http(s)://` / `data:` URIs, justified text, byte-deterministic output, a
185-test golden-file suite, explicit table column-width control, and it runs on
a Linux server or in CI.

**Neither:** AcroForms, JavaScript, encryption, digital signatures,
bookmarks / outlines, tagged PDF / PDF-A, barcodes, charts (both say "render it
as an image"). Both are pre-1.0 with one maintainer.

## Which to reach for

| | Reach for |
|---|---|
| Apple-platform app generating reports / invoices on-device or on a Mac | **PDFBlocks** |
| SwiftUI ergonomics, gradients, vector graphics, good typography | **PDFBlocks** |
| PHP / Laravel backend, a Linux server, a CI pipeline | **declarative-pdf** |
| Reproducible output you can golden-test | **declarative-pdf** |
| Links, PDF import, remote images | **declarative-pdf** |
| Forms, signatures, encryption, barcodes | neither — see [`comparison.md`](comparison.md) |
