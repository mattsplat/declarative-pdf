<?php

declare(strict_types=1);

require __DIR__ . '/../vendor/autoload.php';

use Pdf\Chart\LegendPosition;
use Pdf\Chart\Series;
use Pdf\Color\Color;
use Pdf\Document;
use Pdf\Font\FontFace;
use Pdf\Font\FontRepository;
use Pdf\Geometry\Edges;
use Pdf\Geometry\ShrinkMode;
use Pdf\Geometry\Unit;
use Pdf\Layout\PageContext;
use Pdf\Node\Chart;
use Pdf\Node\Clip;
use Pdf\Node\Container;
use Pdf\Node\Paragraph;
use Pdf\Node\Path;
use Pdf\Node\Table;
use Pdf\Node\TableCell;
use Pdf\Node\TableRow;
use Pdf\Node\TextField;
use Pdf\Node\Checkbox;
use Pdf\Node\Dropdown;
use Pdf\Render\DocumentRenderer;
use Pdf\Style\Border;
use Pdf\Style\ColumnWidth;
use Pdf\Style\FillRule;
use Pdf\Style\GradientStop;
use Pdf\Style\LinearGradient;
use Pdf\Style\Paint;
use Pdf\Style\Style;
use Pdf\Style\StylePatch;
use Pdf\Style\Stylesheet;
use Pdf\Style\TextAlign;
use Pdf\Text\InlineSequence;

/*
 * The grand tour: one document that exercises most of the library --
 * a document-wide house style, a bookmark outline, running furniture,
 * embedded fonts, tables, charts, vector drawing and an interactive form.
 */

$navy = Color::rgb(20, 34, 72);
$accent = Color::rgb(64, 120, 200);
$gold = Color::rgb(196, 158, 92);
$muted = Color::gray(120);

$fixtures = dirname(__DIR__) . '/tests/fixtures';
$fonts = FontRepository::withBundledFonts();
$fonts->register('IBMPlexSans', FontFace::regular(), "{$fixtures}/IBMPlexSans-Regular.json");

$base = (new StylePatch(fontSizePt: 11.0, lineHeight: 1.45, color: Color::rgb(28, 30, 36)))
    ->applyTo(Style::default());

$sheet = (new Stylesheet())
    ->heading(1, new StylePatch(color: $navy, fontSizePt: 26.0, spaceAfterPt: 4.0))
    ->heading(2, new StylePatch(color: $navy, spaceBeforePt: 14.0, spaceAfterPt: 4.0))
    ->paragraph(new StylePatch(align: TextAlign::Justify, spaceAfterPt: 7.0))
    ->class('note', new StylePatch(
        paddingPt: Edges::all(9.0),
        border: new Border(new Edges(0.0, 0.0, 0.0, 3.0), $accent),
        background: Color::rgb(244, 247, 252),
        align: TextAlign::Left,
        spaceBeforePt: 8.0,
        spaceAfterPt: 8.0,
    ));

$doc = Document::create()
    ->using(new DocumentRenderer($fonts))
    ->meta(fn ($m) => $m
        ->title('declarative-pdf — the grand tour')
        ->author('declarative-pdf')
        ->subject('Everything, on one document'))
    ->baseStyle($base)
    ->stylesheet($sheet)
    ->pageNumbers('{n} / {N}', TextAlign::Right, 8.0, $muted)
    ->bookmark('Cover', 'cover', 0)
    ->bookmark('Overview', 'overview', 0)
    ->bookmark('Typography', 'typography', 0)
    ->bookmark('Data', 'data', 0)
    ->bookmark('Drawing', 'drawing', 0)
    ->bookmark('Forms', 'forms', 0);

$header = fn (string $section) => fn (PageContext $c) => new Paragraph(
    "THE GRAND TOUR   ·   {$section}",
    new StylePatch(fontSizePt: 7.5, color: $muted, spaceAfterPt: 0.0),
);

// --- cover -----------------------------------------------------------------
$doc->page(function ($p) use ($navy, $gold, $muted): void {
    $p->units(Unit::Mm);
    $p->anchor('cover');

    $p->place(0, 40, 210, 8, [Path::rectangle(210, 8, Paint::gradient(LinearGradient::horizontal([
        GradientStop::at(0.0, $navy),
        GradientStop::at(1.0, $gold),
    ])))], shrink: ShrinkMode::None);

    $p->spacer(46);
    $p->heading(1, 'declarative-pdf', new StylePatch(fontSizePt: 40.0, color: $navy, spaceAfterPt: 2.0));
    $p->paragraph('The grand tour — one document, most of the library.',
        new StylePatch(fontSizePt: 13.0, color: $muted, align: TextAlign::Left, spaceAfterPt: 24.0));

    $p->paragraph('Contents', new StylePatch(bold: true, color: $navy, spaceAfterPt: 4.0));
    foreach (['Overview', 'Typography', 'Data', 'Drawing', 'Forms'] as $i => $section) {
        $p->paragraph(($i + 1) . '.  ' . $section, new StylePatch(spaceAfterPt: 2.0, align: TextAlign::Left));
    }
});

// --- overview ------------------------------------------------------------
$doc->page(function ($p) use ($header): void {
    $p->header($header('Overview'));
    $p->anchor('overview');
    $p->heading(1, 'Overview');
    $p->paragraph(
        'This file is built the same way every other example is: a tree of '
        . 'immutable nodes handed to a measure / paginate / render / serialise '
        . 'pipeline. The bookmark outline, the running header, the page numbers '
        . 'and the house style are all set once, at the document level.',
    );
    $p->add(new Paragraph(
        'With a fixed clock and producer string the renderer is a pure function '
        . 'from tree to bytes — which is what the golden-file tests rely on.',
        new StylePatch(class: 'note'),
    ));
    $p->heading(2, 'What follows');
    $p->bulletList([
        'Typography — the stylesheet, inline decorations, columns, an embedded font.',
        'Data — an auto-sized table with a totals row, a grouped bar chart, sparklines.',
        'Drawing — gradient fills and clipping to a path.',
        'Forms — AcroForm fields with self-drawn appearance streams.',
    ]);
});

// --- typography --------------------------------------------------------
$doc->page(function ($p) use ($header, $accent): void {
    $p->header($header('Typography'));
    $p->anchor('typography');
    $p->heading(1, 'Typography');

    $p->paragraph(
        InlineSequence::of('One run of text carries ')
            ->withBold('bold')->withRun(', ')->withItalic('italic')->withRun(', ')
            ->withUnderline('underline')->withRun(', ')
            ->withStrikethrough('strike')->withRun(', a ')
            ->withLink('link', 'https://github.com/mattsplat/declarative-pdf')
            ->withRun(', sub')->withSubscript('script')->withRun(' and super')
            ->withSuperscript('script')->withRun('.'),
    );

    $p->paragraph('An embedded OpenType/CFF face, used inline by name:',
        new StylePatch(spaceAfterPt: 2.0));
    $p->paragraph('The quick brown fox jumps over the lazy dog — 0123456789',
        new StylePatch(fontFamily: 'IBMPlexSans', fontSizePt: 15.0, spaceAfterPt: 10.0));

    $p->heading(2, 'Columns');
    $p->columns([
        new Paragraph('The column layout balances a list of blocks across the '
            . 'measure, breaking each column at a sensible point.'),
        new Paragraph('Gutters are configurable; content that overflows the last '
            . 'column continues on the next page.'),
        new Paragraph('Headings keep with the paragraph that follows them, so a '
            . 'section title never strands at a column foot.'),
    ], count: 3, gutterPt: 12.0);
});

// --- data ------------------------------------------------------------
$doc->page(function ($p) use ($header, $navy, $accent): void {
    $p->header($header('Data'));
    $p->anchor('data');
    $p->heading(1, 'Data');

    $right = new StylePatch(align: TextAlign::Right);
    $rows = [
        new TableRow([
            new TableCell('Region', patch: new StylePatch(bold: true)),
            new TableCell('Units', patch: new StylePatch(bold: true, align: TextAlign::Right)),
            new TableCell('Revenue', patch: new StylePatch(bold: true, align: TextAlign::Right)),
        ]),
    ];
    $data = [['North', 1240, 84_300], ['South', 980, 71_100], ['East', 1520, 96_800], ['West', 770, 55_400]];
    $u = 0;
    $r = 0;
    foreach ($data as [$region, $units, $rev]) {
        $u += $units;
        $r += $rev;
        $rows[] = new TableRow([
            new TableCell($region),
            new TableCell(number_format($units), patch: $right),
            new TableCell('$' . number_format($rev), patch: $right),
        ]);
    }
    $rows[] = new TableRow([
        new TableCell('Total', patch: new StylePatch(bold: true)),
        new TableCell(number_format($u), patch: new StylePatch(bold: true, align: TextAlign::Right)),
        new TableCell('$' . number_format($r), patch: new StylePatch(bold: true, align: TextAlign::Right)),
    ]);
    $p->add(new Table($rows, [ColumnWidth::fraction(1.0), ColumnWidth::fixed(70.0), ColumnWidth::fixed(90.0)],
        headerRows: 1, headerBackground: Color::rgb(232, 237, 244)));

    $p->heading(2, 'Trend');
    $p->chart(Chart::bar(
        [
            Series::of('This year', [64, 71, 68, 82, 90, 88], $accent),
            Series::of('Last year', [58, 60, 63, 70, 74, 77], Color::rgb(170, 195, 225)),
        ],
        ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun'],
        160.0, 46.0, Unit::Mm, LegendPosition::Bottom,
    ));
});

// --- drawing --------------------------------------------------------
$doc->page(function ($p) use ($header, $navy, $gold): void {
    $p->header($header('Drawing'));
    $p->anchor('drawing');
    $p->units(Unit::Mm);
    $p->heading(1, 'Drawing');
    $p->paragraph('Vector linework painted with solid or gradient fills, and '
        . 'clipping to an arbitrary path.');

    $band = [
        GradientStop::at(0.0, Color::fromHex('#f9d976')),
        GradientStop::at(0.5, Color::fromHex('#e96443')),
        GradientStop::at(1.0, $navy),
    ];
    $p->path(Path::rectangle(160, 20, Paint::gradient(LinearGradient::horizontal($band)),
        patch: new StylePatch(spaceAfterPt: 6.0)));
    $p->path(Path::ellipse(160, 20, Paint::gradient(\Pdf\Style\RadialGradient::centered([
        GradientStop::at(0.0, Color::white()),
        GradientStop::at(1.0, $navy),
    ])), patch: new StylePatch(spaceAfterPt: 10.0)));

    $p->heading(2, 'Clipped to a star');
    $p->paragraph('The gradient panel below is masked to a five-pointed star '
        . '(even-odd fill rule); a Clip node never splits across a page.',
        new StylePatch(spaceAfterPt: 4.0));
    $star = [];
    for ($k = 0; $k < 5; $k++) {
        $a = deg2rad($k * 144 - 90);
        $star[] = new \Pdf\Geometry\Point(40 + 40 * cos($a), 40 + 40 * sin($a));
    }
    $p->add(new Clip(
        Path::polygon($star, Paint::filled(Color::black())),
        [Path::rectangle(80, 80, Paint::gradient(LinearGradient::vertical($band)))],
        FillRule::EvenOdd,
    ));
});

// --- forms --------------------------------------------------------
$doc->page(function ($p) use ($header, $navy, $muted): void {
    $p->header($header('Forms'));
    $p->anchor('forms');
    $p->heading(1, 'Forms');
    $p->paragraph('AcroForm fields that draw their own borders and values, so '
        . 'they work in every viewer without /NeedAppearances.');

    $p->field(new TextField(name: 'demo.name', label: 'Name'));
    $p->field(new TextField(name: 'demo.ref', label: 'Reference', maxLength: 8, comb: true, widthPt: 170.0));
    $p->field(new Dropdown(name: 'demo.plan', label: 'Plan',
        options: ['m' => 'Monthly', 'a' => 'Annual'], value: 'a'));
    $p->field(new Checkbox(name: 'demo.ok', label: 'I have read the grand tour.', checked: true));

    $p->add(new Container([
        new Paragraph('That is the tour. Each section here has a dedicated example '
            . 'file — styled.php, table.php, chart.php, shapes.php, form.php — that '
            . 'goes deeper.', new StylePatch(color: $muted, spaceAfterPt: 0.0)),
    ], new StylePatch(
        paddingPt: Edges::all(9.0),
        background: Color::rgb(246, 246, 248),
        spaceBeforePt: 14.0,
    )));
});

$doc->save(__DIR__ . '/showcase.pdf');

echo 'Wrote ' . __DIR__ . "/showcase.pdf\n";
