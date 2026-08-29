# Getting started

## Install

```
composer require mattsplat/declarative-pdf
```

Requires PHP 8.3+, `ext-zlib`, `ext-mbstring`. `ext-gd` is needed for GIF/WebP
images; `ext-iconv` for font encodings other than Windows-1252.

## Hello world

```php
use Pdf\Document;

Document::create()
    ->page(fn ($p) => $p
        ->heading(1, 'Hello world')
        ->paragraph('The layout engine wraps this text, breaks pages, and '
            . 'positions everything — you never move a cursor.'))
    ->save('hello.pdf');
```

## The model

You describe a document as an **immutable tree of nodes**. A `Document` has one
or more `Page`s; each page has a stack of block nodes (`Heading`, `Paragraph`,
`Table`, `Container`, …). Nothing is positioned by you — a measure/paginate/
render pipeline does that.

There are two ways to build the tree.

### Fluent builder

```php
use Pdf\Document;
use Pdf\Style\StylePatch;
use Pdf\Style\TextAlign;

$pdf = Document::create()
    ->meta(fn ($m) => $m->title('Report')->author('Jo'))
    ->page(fn ($p) => $p
        ->heading(1, 'Overview')
        ->paragraph('Body text.')
        ->paragraph('Justified.', new StylePatch(align: TextAlign::Justify)))
    ->toString();          // ← the PDF bytes
```

### Direct value objects

```php
use Pdf\Document;
use Pdf\Node\{Document as Tree, Page, PageMaster, Heading, Paragraph, Meta};
use Pdf\Text\InlineSequence;

$tree = new Tree(
    meta: new Meta(title: 'Report'),
    pages: [new Page(new PageMaster(), [
        new Heading(1, InlineSequence::of('Overview')),
        new Paragraph('Body text.'),
    ])],
);

file_put_contents('out.pdf', Document::render($tree));
```

The builder just assembles the same value objects — mix and match freely
(`$page->add(new Table(...))`).

## Output

`Document::create()->…` returns a `DocumentBuilder`. Finish with:

| call | result |
|---|---|
| `->save('out.pdf')` | write to a file |
| `->toString()` | return the PDF as a string |
| `->output()->inline('doc.pdf')` | stream to the browser inline (`Content-Disposition: inline`) |
| `->output()->download('doc.pdf')` | stream as an attachment |

## Styling

Every block takes an optional `StylePatch` — a sparse set of overrides; anything
left `null` is inherited from the parent.

```php
use Pdf\Color\Color;
use Pdf\Style\StylePatch;

$p->paragraph('Warning', new StylePatch(
    bold: true,
    color: Color::rgb(180, 30, 30),
    fontSizePt: 14,
    spaceAfterPt: 12,
));
```

Document-wide defaults: `->baseStyle(Style)`. Per-node-type rules:
`->stylesheet((new Stylesheet())->heading(1, $patch)->paragraph($patch))`.

## Determinism

With `compress: false` and a fixed clock the output is byte-stable — useful for
golden-file tests:

```php
use Pdf\Render\DocumentRenderer;
use Pdf\Font\FontRepository;
use Pdf\Support\FixedClock;

$renderer = new DocumentRenderer(
    FontRepository::withBundledFonts(),
    clock: FixedClock::at('2026-01-01T00:00:00+00:00'),
    compress: false,
);
Document::create()->using($renderer)->page(...)->save('out.pdf');
```

## Next

- [Cookbook](cookbook.md) — task-oriented recipes
- [Reference](reference.md) — every method, node and option
- [FPDF vs. declarative](fpdf-vs-declarative.md) — the 7 FPDF tutorials, side by side
- [Architecture](architecture.md)
