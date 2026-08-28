# FPDF vs. this library — side by side

FPDF is an **imperative cursor machine**: you add a page, set a font, and emit
cells one after another; you compute every width, y-position and page break
yourself, and you subclass the class to hook headers, footers and page breaks.

This library is **declarative**: you describe the document as an immutable tree
of nodes and a layout engine measures, paginates and renders it. You never move
a cursor, never call `AddPage()`, never override a method.

The examples below re-implement FPDF's seven tutorials. Each declarative version
is a trimmed form of the matching script in [`examples/`](../examples).

---

## 1. Hello world

**FPDF**

```php
require('fpdf.php');

$pdf = new FPDF();
$pdf->AddPage();
$pdf->SetFont('Arial', 'B', 16);
$pdf->Cell(40, 10, 'Hello World!');
$pdf->Output();
```

**Declarative**

```php
use Pdf\Document;

Document::create()
    ->page(fn ($p) => $p->heading(1, 'Hello World!'))
    ->save('hello.pdf');
```

`SetFont` + `Cell` at a fixed size become a semantic `heading(1, …)`; the
default style (Helvetica 12 pt, h1 ×2) supplies the rest. `Cell` with a hard-
coded 40 × 10 box is gone — the text is measured and placed.

---

## 2. Header, footer and page numbers

**FPDF** — subclass and override, plus the `{nb}` placeholder:

```php
class PDF extends FPDF
{
    function Header()
    {
        $this->Image('logo.png', 10, 6, 30);
        $this->SetFont('Arial', 'B', 15);
        $this->Cell(80);
        $this->Cell(30, 10, 'Title', 1, 0, 'C');
        $this->Ln(20);
    }

    function Footer()
    {
        $this->SetY(-15);
        $this->SetFont('Arial', 'I', 8);
        $this->Cell(0, 10, 'Page ' . $this->PageNo() . '/{nb}', 0, 0, 'C');
    }
}

$pdf = new PDF();
$pdf->AliasNbPages();
$pdf->AddPage();
$pdf->SetFont('Times', '', 12);
for ($i = 1; $i <= 40; $i++) {
    $pdf->Cell(0, 10, 'Printing line number ' . $i, 0, 1);
}
$pdf->Output();
```

**Declarative** — closures on the page master, real page count:

```php
use Pdf\Document;
use Pdf\Layout\PageContext;
use Pdf\Node\{ImageBlock, Paragraph};
use Pdf\Style\{StylePatch, TextAlign};

Document::create()
    ->page(function ($p) {
        $p->header(fn (PageContext $c) => [
            ImageBlock::of('logo.png', width: 30),
            new Paragraph('Title', new StylePatch(align: TextAlign::Center, bold: true, fontSizePt: 15)),
        ]);
        $p->footer(fn (PageContext $c) => new Paragraph(
            "Page {$c->pageNumber}/{$c->pageCount}",
            new StylePatch(align: TextAlign::Center, italic: true, fontSizePt: 8),
        ));

        for ($i = 1; $i <= 40; $i++) {
            $p->paragraph("Printing line number {$i}");
        }
    })
    ->save('report.pdf');
```

No subclass, no `AliasNbPages()` / `{nb}` — the whole document is laid out
before any band renders, so `$c->pageCount` is the real total. Band height is
reserved from the content area automatically.

---

## 3. Chapters, `MultiCell`, colours, metadata

**FPDF**

```php
class PDF extends FPDF
{
    function ChapterTitle($num, $label)
    {
        $this->SetFont('Arial', '', 12);
        $this->SetFillColor(200, 220, 255);
        $this->Cell(0, 6, "Chapter $num : $label", 0, 1, 'L', true);
        $this->Ln(4);
    }

    function ChapterBody($file)
    {
        $this->SetFont('Times', '', 12);
        $this->MultiCell(0, 5, file_get_contents($file));
        $this->Ln();
    }

    function PrintChapter($num, $title, $file)
    {
        $this->AddPage();
        $this->ChapterTitle($num, $title);
        $this->ChapterBody($file);
    }
}

$pdf = new PDF();
$pdf->SetTitle('20000 Leagues Under the Seas');
$pdf->SetAuthor('Jules Verne');
$pdf->PrintChapter(1, 'A RUNAWAY REEF', '20k_c1.txt');
$pdf->PrintChapter(2, 'THE PROS AND CONS', '20k_c2.txt');
$pdf->Output();
```

**Declarative**

```php
use Pdf\Color\Color;
use Pdf\Document;
use Pdf\Style\StylePatch;

$chapters = [
    [1, 'A RUNAWAY REEF', '20k_c1.txt'],
    [2, 'THE PROS AND CONS', '20k_c2.txt'],
];

$doc = Document::create()->meta(fn ($m) => $m
    ->title('20000 Leagues Under the Seas')->author('Jules Verne'));

foreach ($chapters as [$num, $title, $file]) {
    $doc->page(fn ($p) => $p
        ->heading(2, "Chapter {$num} : {$title}",
            new StylePatch(background: Color::rgb(200, 220, 255), fontSizePt: 12))
        ->paragraph(file_get_contents($file)));
}

$doc->save('chapters.pdf');
```

`MultiCell` wrapping and its manual page breaks disappear — a `paragraph`
wraps and paginates. `SetFillColor` + a filled `Cell` becomes
`background:` on the heading's style. `SetTitle` / `SetAuthor` → `meta(...)`.

---

## 4. Multi-column layout

**FPDF** — a `protected $col` field and an `AcceptPageBreak()` override that
redirects the "page break" into a column change:

```php
class PDF extends FPDF
{
    protected $col = 0;
    protected $y0;

    function SetCol($col)
    {
        $this->col = $col;
        $x = 10 + $col * 65;
        $this->SetLeftMargin($x);
        $this->SetX($x);
    }

    function AcceptPageBreak()
    {
        if ($this->col < 2) {
            $this->SetCol($this->col + 1);
            $this->SetY($this->y0);
            return false;          // not a page break — next column
        }
        $this->SetCol(0);
        return true;               // real page break
    }

    function ChapterBody($file)
    {
        $this->SetFont('Times', '', 12);
        $this->MultiCell(60, 5, file_get_contents($file));
    }
}
```

**Declarative** — a first-class block:

```php
use Pdf\Document;
use Pdf\Node\Paragraph;

Document::create()
    ->page(fn ($p) => $p
        ->heading(1, 'A Runaway Reef')
        ->columns([
            new Paragraph(file_get_contents('20k_c1.txt')),
        ], count: 3, gutterPt: 15))
    ->save('columns.pdf');
```

`Columns` balances when the block fits a page and fills column-then-page when it
overflows.

---

## 5. Tables

**FPDF** — hard-coded column widths, a `Cell` grid, and you redraw the header
after every page break yourself:

```php
class PDF extends FPDF
{
    function ImprovedTable($header, $data)
    {
        $w = array(40, 35, 40, 45);
        for ($i = 0; $i < count($header); $i++) {
            $this->Cell($w[$i], 7, $header[$i], 1, 0, 'C');
        }
        $this->Ln();
        foreach ($data as $row) {
            $this->Cell($w[0], 6, $row[0], 'LR');
            $this->Cell($w[1], 6, $row[1], 'LR');
            $this->Cell($w[2], 6, number_format($row[2]), 'LR', 0, 'R');
            $this->Cell($w[3], 6, number_format($row[3]), 'LR', 0, 'R');
            $this->Ln();
        }
        $this->Cell(array_sum($w), 0, '', 'T');
    }
}

$pdf = new PDF();
$pdf->AddPage();
$pdf->SetFont('Arial', '', 14);
$pdf->ImprovedTable($header, $pdf->LoadData('countries.txt'));
$pdf->Output();
```

**Declarative** — column widths computed from content; the header repeats on
every page automatically:

```php
use Pdf\Document;
use Pdf\Node\{Table, TableRow, TableCell};
use Pdf\Style\{ColumnWidth, StylePatch, TextAlign};

$rows = [new TableRow(['Country', 'Capital', 'Area (km2)', 'Pop. (thousands)'])];
foreach (file('countries.txt', FILE_IGNORE_NEW_LINES) as $line) {
    [$country, $capital, $area, $pop] = explode(';', $line);
    $rows[] = new TableRow([
        $country,
        $capital,
        new TableCell(number_format((int) $area), patch: new StylePatch(align: TextAlign::Right)),
        new TableCell(number_format((int) $pop), patch: new StylePatch(align: TextAlign::Right)),
    ]);
}

Document::create()
    ->page(fn ($p) => $p->add(new Table(
        $rows,
        [ColumnWidth::auto(), ColumnWidth::auto(), ColumnWidth::fixed(90), ColumnWidth::fixed(100)],
        headerRows: 1,
    )))
    ->save('table.pdf');
```

---

## 6. Inline HTML

**FPDF** — you copy tuto6's ~60-line `WriteHTML` parser into a subclass:

```php
class PDF extends FPDF
{
    protected $B = 0, $I = 0, $U = 0, $HREF = '';

    function WriteHTML($html)
    {
        $a = preg_split('/<(.*)>/U', $html, -1, PREG_SPLIT_DELIM_CAPTURE);
        foreach ($a as $i => $e) {
            if ($i % 2 == 0) {
                $this->HREF ? $this->PutLink($this->HREF, $e) : $this->Write(5, $e);
            } elseif ($e[0] == '/') {
                $this->CloseTag(strtoupper(substr($e, 1)));
            } else {
                // ... parse tag + attributes, call OpenTag ...
            }
        }
    }
    // ... OpenTag / CloseTag / SetStyle / PutLink ...
}

$pdf = new PDF();
$pdf->AddPage();
$pdf->SetFont('Arial', '', 12);
$pdf->WriteHTML('Text with <b>bold</b>, <i>italic</i> and <a href="http://www.fpdf.org">a link</a>.');
$pdf->Output();
```

**Declarative** — built in:

```php
use Pdf\Document;

Document::create()
    ->page(fn ($p) => $p->html(
        'Text with <b>bold</b>, <i>italic</i> and '
        . '<a href="http://www.fpdf.org">a link</a>. Also x<sup>2</sup> and <br>a break.'))
    ->save('html.pdf');
```

`Pdf\Text\Html::toInline()` covers `b`/`i`/`u`/`s`/`sup`/`sub`/`a`/`br`.

---

## 7. A custom embedded font

**FPDF**

```php
require('fpdf.php');

$pdf = new FPDF();
$pdf->AddFont('CevicheOne', '', 'CevicheOne-Regular.json');
$pdf->AddPage();
$pdf->SetFont('CevicheOne', '', 45);
$pdf->Write(10, 'Enjoy new fonts with FPDF!');
$pdf->Output();
```

**Declarative** — register on a `FontRepository`, then select it by style:

```php
use Pdf\Document;
use Pdf\Font\{FontRepository, FontStyle};
use Pdf\Render\DocumentRenderer;
use Pdf\Style\StylePatch;

$fonts = FontRepository::withBundledFonts();
$fonts->register('CevicheOne', FontStyle::Regular, 'CevicheOne-Regular.json');

Document::create()
    ->using(new DocumentRenderer($fonts))
    ->page(fn ($p) => $p->paragraph('Enjoy new fonts!',
        new StylePatch(fontFamily: 'CevicheOne', fontSizePt: 45)))
    ->save('custom-font.pdf');
```

The `.json` is still produced by `php tools/makefont/makefont.php Font.ttf cp1252`,
which also handles `.otf` fonts with PostScript (CFF) outlines — FPDF 1.9
rejected those outright.

---

## Concept mapping

| FPDF | this library |
|---|---|
| `new FPDF('P','mm','A4')` | `$p->size(PageSize::a4())` (mm is only for `place*()` coords) |
| `$pdf->AddPage()` | automatic; `$p->pageBreak()` to force one |
| `SetFont('Arial','B',16)` then `Cell` | `paragraph($t, new StylePatch(bold: true, fontSizePt: 16))` or `heading(n, $t)` |
| `MultiCell(0, 5, $text)` | `paragraph($text)` — wraps and paginates |
| `Write(5, $text)` | `paragraph($text)` |
| `Ln(10)` | `spacer(10)` or `spaceAfterPt` on the style |
| `SetTextColor` / `SetFillColor` around a `Cell` | `color:` / `background:` on a `StylePatch` |
| `Rect()` + `Cell` to make a panel | `container([...], new StylePatch(padding:, border:, background:))` |
| subclass + `Header()` / `Footer()` | `$p->header(fn ($ctx) => …)` / `$p->footer(…)` |
| `AliasNbPages()` + `{nb}` | `$ctx->pageCount` (already the real total) |
| override `AcceptPageBreak()` (tuto4) | `$p->columns([...], count: n)` |
| hand-built `Cell` table + manual header redraw (tuto5) | `Table` with `ColumnWidth` + `repeatHeader` |
| copy the `WriteHTML` add-on (tuto6) | `$p->html('…')` / `Html::toInline()` |
| `AddFont(...)` (tuto7) | `FontRepository::register(...)` + `fontFamily:` |
| `Image('logo.png', 10, 8, 30)` (absolute) | `$p->image('logo.png', width: 30)` (flows) or `$p->placeImage(x, y, w, h, …)` (absolute) |
| `AddLink()` / `SetLink()` / `Link()` | `InlineSequence::withLink('t', '#name')` + `$p->anchor('name')` |
| `$pdf->Output('F', 'out.pdf')` | `$doc->save('out.pdf')` |
| `$pdf->Output('S')` | `$doc->toString()` |
| `$pdf->Output('I' \| 'D')` | `$doc->output()->inline()` / `->download()` |
| FPDI (`setSourceFile` / `importPage` / `useTemplate`) | `$p->placePdf(x, y, w, h, 'src.pdf', page: 1)` |

See [`porting.md`](porting.md) for the same mapping in prose, and
[`reference.md`](reference.md) for the full API.
