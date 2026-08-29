<?php

declare(strict_types=1);

require __DIR__ . '/../vendor/autoload.php';

use Pdf\Color\Color;
use Pdf\Document;
use Pdf\Geometry\Edges;
use Pdf\Layout\PageContext;
use Pdf\Node\Paragraph;
use Pdf\Style\Border;
use Pdf\Style\Style;
use Pdf\Style\StylePatch;
use Pdf\Style\Stylesheet;
use Pdf\Style\TextAlign;
use Pdf\Text\InlineSequence;

/*
 * A house style, applied once at the document level.
 *
 * `baseStyle()` sets the document-wide defaults; the `Stylesheet` layers
 * per-node-type rules and named class rules on top. The resolver order is:
 *   base style  ->  node-type rule  ->  class rule(s)  ->  the node's own patch
 * so a node can always still override the sheet inline.
 */

$navy = Color::rgb(28, 42, 84);
$rule = Color::rgb(196, 168, 108);

$base = (new StylePatch(
    fontFamily: 'Times',
    fontSizePt: 11.5,
    lineHeight: 1.5,
    color: Color::rgb(30, 30, 34),
))->applyTo(Style::default());

$sheet = (new Stylesheet())
    ->heading(1, new StylePatch(fontFamily: 'Helvetica', color: $navy, fontSizePt: 30.0, spaceAfterPt: 2.0))
    ->heading(2, new StylePatch(fontFamily: 'Helvetica', color: $navy, spaceBeforePt: 16.0, spaceAfterPt: 4.0))
    ->heading(3, new StylePatch(fontFamily: 'Helvetica', color: Color::gray(70), spaceBeforePt: 10.0))
    ->paragraph(new StylePatch(align: TextAlign::Justify, spaceAfterPt: 8.0))
    ->class('lead', new StylePatch(
        fontFamily: 'Helvetica',
        fontSizePt: 13.5,
        color: Color::gray(70),
        align: TextAlign::Left,
        lineHeight: 1.4,
    ))
    ->class('quote', new StylePatch(
        fontSizePt: 15.0,
        italic: true,
        color: $navy,
        align: TextAlign::Left,
        paddingPt: new Edges(4.0, 0.0, 4.0, 16.0),
        border: new Border(new Edges(0.0, 0.0, 0.0, 3.0), $rule),
        spaceBeforePt: 10.0,
        spaceAfterPt: 10.0,
    ));

Document::create()
    ->meta(fn ($m) => $m->title('On deterministic documents')->author('declarative-pdf'))
    ->baseStyle($base)
    ->stylesheet($sheet)
    ->pageNumbers('{n}', TextAlign::Center, 9.0, Color::gray(140))
    ->page(function ($p) use ($navy, $rule): void {
        $p->header(fn (PageContext $c) => new Paragraph(
            "THE LAYOUT REVIEW  ·  ISSUE 3",
            new StylePatch(fontFamily: 'Helvetica', fontSizePt: 7.5, color: Color::gray(140), spaceAfterPt: 0.0),
        ));

        $p->heading(1, 'Same input, same bytes');
        $p->add(new Paragraph(
            'Why a PDF library should be boringly reproducible, and what it takes '
            . 'to get there.',
            new StylePatch(class: 'lead', spaceAfterPt: 14.0),
        ));

        $p->paragraph(
            InlineSequence::of('A document generator is easiest to trust when the '
                . 'same tree always renders to the same file. That property, '
                . 'determinism, is what lets you diff two builds, cache a result, or '
                . 'lock output behind a golden-file test. It is not free: timestamps, '
                . 'hash-ordered dictionaries and object ids all leak entropy into the '
                . 'bytes.'),
        );

        $p->add(new Paragraph(
            'Given a fixed clock and a fixed producer string, the renderer is a '
            . 'pure function from tree to bytes.',
            new StylePatch(class: 'quote'),
        ));

        $p->heading(2, 'Where nondeterminism hides');
        $p->orderedList([
            'Creation and modification dates in the Info dictionary.',
            'The XMP packet, if one is emitted.',
            InlineSequence::of('Any id derived from ')
                ->withRun('spl_object_id()', new StylePatch(fontFamily: 'Courier', fontSizePt: 10.0))
                ->withRun(' or a memory address.'),
            'Iteration order over an associative structure that was built from '
                . 'unsorted input.',
        ], start: 1, patch: new StylePatch(spaceAfterPt: 10.0));

        $p->heading(2, 'Formulae still work');
        $p->paragraph(
            InlineSequence::of('Inline decorations compose: water is H')
                ->withSubscript('2')
                ->withRun('O, the mass-energy relation is E = mc')
                ->withSuperscript('2')
                ->withRun(', and you can mix ')
                ->withBold('bold')
                ->withRun(', ')
                ->withItalic('italic')
                ->withRun(' and a ')
                ->withLink('link', 'https://github.com/mattsplat/declarative-pdf')
                ->withRun(' in one run.'),
        );

        $p->rule(1.0, $rule);
        $p->paragraph(
            'Set in Times over a Helvetica display face, justified, with a gold '
            . 'rule and a hanging pull-quote, all from the stylesheet above.',
            new StylePatch(fontFamily: 'Helvetica', fontSizePt: 8.5, color: Color::gray(130), align: TextAlign::Center),
        );
    })
    ->save(__DIR__ . '/styled.pdf');

echo 'Wrote ' . __DIR__ . "/styled.pdf\n";
