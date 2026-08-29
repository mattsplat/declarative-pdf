<?php

declare(strict_types=1);

require __DIR__ . '/../vendor/autoload.php';

use Pdf\Color\Color;
use Pdf\Geometry\Edges;
use Pdf\Document;
use Pdf\Layout\PageContext;
use Pdf\Node\Paragraph;
use Pdf\Style\Border;
use Pdf\Style\StylePatch;
use Pdf\Style\TextAlign;
use Pdf\Text\InlineSequence;

/*
 * FPDF tutorial 1 ("Hello World"), reimagined.
 *
 * The imperative original is six lines: AddPage, SetFont, Cell, Output. This
 * one is longer because it actually says something — but notice there is still
 * no cursor, no measuring, no page-break bookkeeping. You describe blocks; the
 * layout engine places them and breaks the page where it must.
 */

$ink = Color::rgb(24, 33, 54);
$muted = Color::gray(110);

Document::create()
    ->meta(fn ($m) => $m
        ->title('Hello from declarative-pdf')
        ->author('declarative-pdf')
        ->subject('The five-minute introduction')
        ->creator('examples/hello.php'))
    ->pageNumbers('{n} / {N}', TextAlign::Right, 8.5, $muted)
    ->page(function ($p) use ($ink, $muted): void {
        $p->header(fn (PageContext $c) => new Paragraph(
            'declarative-pdf',
            new StylePatch(fontSizePt: 8.0, color: $muted, spaceAfterPt: 0.0),
        ));

        $p->heading(1, 'Hello, World', new StylePatch(color: $ink));
        $p->paragraph(
            'FPDF drove a cursor around the page with AddPage, SetFont and Cell. '
            . 'This is a from-scratch reimagining: a document is an immutable tree '
            . 'of nodes, and a "measure, paginate, render, serialise" pipeline '
            . 'decides where everything lands.',
            new StylePatch(align: TextAlign::Justify, lineHeight: 1.5, spaceAfterPt: 10.0),
        );

        $p->paragraph(
            InlineSequence::of('One paragraph can still carry ')
                ->withBold('bold')
                ->withRun(', ')
                ->withItalic('italic')
                ->withRun(', ')
                ->withUnderline('underlined')
                ->withRun(' and ')
                ->withLink('linked', 'https://github.com/mattsplat/declarative-pdf')
                ->withRun(' runs. A hard break')
                ->withBreak()
                ->withRun('starts a new line without ending the paragraph, and the '
                    . 'greedy line breaker fills each line the way MultiCell did.'),
            new StylePatch(align: TextAlign::Justify, lineHeight: 1.5, spaceAfterPt: 14.0),
        );

        $p->container([
            new Paragraph(
                'What the pipeline does for you',
                new StylePatch(bold: true, color: $ink, spaceAfterPt: 0.0),
            ),
        ], new StylePatch(
            paddingPt: new Edges(8.0, 12.0, 8.0, 12.0),
            border: new Border(new Edges(0.0, 0.0, 0.0, 2.0), Color::rgb(70, 110, 200)),
            background: Color::rgb(244, 247, 252),
            spaceAfterPt: 8.0,
        ));

        $p->bulletList([
            'Measures every block against the available width.',
            'Splits paragraphs, containers and tables across page breaks, keeping '
                . 'headings with the text that follows them and honouring widow / '
                . 'orphan limits.',
            'Flows text into columns, wraps it around inline images, and justifies '
                . 'it when you ask.',
            'Emits a deterministic PDF — the same input bytes out identical bytes, '
                . 'which is what makes the golden-file tests possible.',
        ], new StylePatch(spaceAfterPt: 12.0));

        $p->rule(0.75, Color::gray(210));
        $p->paragraph(
            'Every other file in this folder builds on that idea. Start with '
            . 'styled.php for the type system, table.php for automatic column '
            . 'sizing, and showcase.php for all of it at once.',
            new StylePatch(fontSizePt: 9.5, color: $muted),
        );
    })
    ->save(__DIR__ . '/hello.pdf');

echo 'Wrote ' . __DIR__ . "/hello.pdf\n";
