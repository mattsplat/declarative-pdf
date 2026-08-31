<?php

declare(strict_types=1);

require __DIR__ . '/../vendor/autoload.php';

use Pdf\Builder\CoverLayout;
use Pdf\Color\Color;
use Pdf\Document;
use Pdf\Geometry\Unit;
use Pdf\Node\Callout;
use Pdf\Node\Card;
use Pdf\Node\DefinitionList;
use Pdf\Node\ImageBlock;
use Pdf\Node\Paragraph;
use Pdf\Node\Row;
use Pdf\Style\ColumnWidth;
use Pdf\Style\Edge;
use Pdf\Style\StylePatch;
use Pdf\Style\TextAlign;
use Pdf\Style\VerticalAlign;

/*
 * The flow-content components: a generated cover page, then Card, Callout, Row
 * (a horizontal stack) and DefinitionList on one flowing page. Each is a
 * Component — it expands to ordinary nodes, so it composes anywhere a block
 * does and needs no special handling from the layout engine.
 */

$fixtures = dirname(__DIR__) . '/tests/fixtures';
$navy = Color::rgb(19, 33, 68);
$muted = Color::gray(115);

$doc = Document::create()
    ->meta(fn ($m) => $m->title('Flow components')->author('declarative-pdf'))
    ->pageNumbers('{n} / {N}', TextAlign::Right, 8.0, $muted)
    ->cover(fn ($c) => $c
        ->layout(CoverLayout::BottomBand)
        ->logo("{$fixtures}/dot-rgba.png")
        ->title('Flow components')
        ->subtitle('Card · Callout · Row · DefinitionList')
        ->line('Reference build', 'declarative-pdf', date('Y-m-d')));

$doc->page(function ($p) use ($navy, $muted, $fixtures): void {
    $p->heading(1, 'Components', new StylePatch(color: $navy));
    $p->paragraph(
        'These four expand to tables, containers, paragraphs and rules — the '
        . 'same primitives you would otherwise wire up by hand on every page.',
        new StylePatch(spaceAfterPt: 10.0),
    );

    $p->heading(2, 'Card', new StylePatch(color: $navy));
    $p->component(new Card(
        [new Paragraph('A titled, framed panel. The title carries a hairline rule; '
            . 'the body is any block content. Padding, border and background are knobs.')],
        title: 'Quarterly summary',
        background: Color::rgb(249, 250, 252),
    ));

    $p->heading(2, 'Callout', new StylePatch(color: $navy, spaceBeforePt: 14.0));
    $p->component(new Callout(
        'A tinted aside with an accent edge. Give it a string or blocks, pick the '
        . 'edge and colours, and it splits across pages like any container.',
        title: 'Note',
    ));
    $p->component(new Callout(
        'Accent on a different edge, a warmer tint, no title.',
        tint: Color::rgb(252, 248, 240),
        accent: Color::rgb(196, 158, 92),
        accentEdge: Edge::Top,
    ));

    $p->heading(2, 'Row', new StylePatch(color: $navy, spaceBeforePt: 14.0));
    $p->paragraph('A horizontal stack — children side by side with a fixed gap, '
        . 'each column content-sized unless a width is given.', new StylePatch(spaceAfterPt: 6.0));
    $p->component(new Row([
        new ImageBlock("{$fixtures}/dot-rgba.png", heightPt: Unit::Mm->toPoints(12.0)),
        new Paragraph('A wordmark next to a mark: the image column sizes to the '
            . 'picture, the text column takes the rest.', new StylePatch(spaceAfterPt: 0.0)),
    ], gapPt: 12.0, align: VerticalAlign::Middle, widths: [1 => ColumnWidth::fraction(1.0)]));

    $p->heading(2, 'DefinitionList', new StylePatch(color: $navy, spaceBeforePt: 14.0));
    $p->component(new DefinitionList([
        'Engine' => 'measure, paginate, render, serialise — over an immutable node tree.',
        'Units' => 'PostScript points internally; mm / cm / in converted at the API boundary.',
        'Output' => 'Byte-stable with a fixed clock and producer string — the golden tests rely on it.',
    ]));

    $p->component(new DefinitionList(
        [
            ['Status', 'Shipped'],
            ['Owner', 'Layout guild'],
        ],
        termWidth: ColumnWidth::fixed(90.0),
        bodyStyle: new StylePatch(color: $muted),
    ));
});

$doc->save(__DIR__ . '/components.pdf');

echo 'Wrote ' . __DIR__ . "/components.pdf\n";
