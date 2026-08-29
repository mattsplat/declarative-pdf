<?php

declare(strict_types=1);

require __DIR__ . '/../vendor/autoload.php';

use Pdf\Color\Color;
use Pdf\Document;
use Pdf\Node\Paragraph;
use Pdf\Node\Table;
use Pdf\Node\TableCell;
use Pdf\Node\TableRow;
use Pdf\Style\ColumnWidth;
use Pdf\Style\StylePatch;
use Pdf\Text\Html;

/*
 * The declarative answer to FPDF tutorial 6's WriteHTML().
 *
 * There is no block HTML here — headings, lists and tables are real nodes.
 * `->html()` (and `Html::toInline()`) handle the *inline* run: bold, italic,
 * underline, strike, super/subscript, links, <br>, and HTML entities. Anything
 * it does not recognise is passed through as literal text.
 */

$ink = Color::rgb(20, 30, 40);
$code = new StylePatch(fontFamily: 'Courier', fontSizePt: 9.5, color: Color::rgb(150, 40, 90));

$prose = <<<'HTML'
You can set text <b>bold</b>, <i>italic</i>, <u>underlined</u> or
<s>struck&nbsp;through</s>, nest them like <b>bold <i>and italic</i></b>,
write <a href="https://example.com">links</a>, formulae such as
E&nbsp;=&nbsp;mc<sup>2</sup> and H<sub>2</sub>O, and force a break<br>
mid-paragraph. Entities decode: &amp; &copy; &mdash; &ldquo;quoted&rdquo;.
An unknown tag like &lt;marquee&gt; is kept verbatim.
HTML;

Document::create()
    ->meta(fn ($m) => $m->title('Inline HTML')->subject('WriteHTML, declarative'))
    ->page(function ($p) use ($ink, $code, $prose): void {
        $p->heading(1, 'Inline HTML', new StylePatch(color: $ink));

        $p->heading(3, 'A paragraph of marked-up prose');
        $p->html($prose, new StylePatch(lineHeight: 1.5, spaceAfterPt: 12.0));

        $p->heading(3, 'The same, built with Html::toInline()');
        $p->add(new Paragraph(
            Html::toInline('Programmatic: <b>a <i>nested</i></b> run, an '
                . '<a href="#tags">internal link</a>, and a hard break.<br>Second line.'),
            new StylePatch(lineHeight: 1.5, spaceAfterPt: 14.0),
        ));

        $p->anchor('tags');
        $p->heading(3, 'Recognised tags');
        $p->add(new Table(
            [
                new TableRow([
                    new TableCell('Tag', patch: new StylePatch(bold: true)),
                    new TableCell('Renders as', patch: new StylePatch(bold: true)),
                ]),
                new TableRow([new TableCell('<b>, <strong>', patch: $code), new TableCell(Html::toInline('<b>bold</b>'))]),
                new TableRow([new TableCell('<i>, <em>', patch: $code), new TableCell(Html::toInline('<i>italic</i>'))]),
                new TableRow([new TableCell('<u>', patch: $code), new TableCell(Html::toInline('<u>underline</u>'))]),
                new TableRow([new TableCell('<s>, <del>', patch: $code), new TableCell(Html::toInline('<s>strike</s>'))]),
                new TableRow([new TableCell('<sup>, <sub>', patch: $code), new TableCell(Html::toInline('x<sup>2</sup> and x<sub>i</sub>'))]),
                new TableRow([new TableCell('<a href>', patch: $code), new TableCell(Html::toInline('<a href="https://example.com">a link</a>'))]),
                new TableRow([new TableCell('<br>', patch: $code), new TableCell('an explicit line break')]),
            ],
            [ColumnWidth::fixed(110.0), ColumnWidth::fraction(1.0)],
            headerRows: 1,
            headerBackground: Color::rgb(236, 240, 246),
        ));

        $p->rule(0.5, Color::gray(210));
        $p->paragraph(
            'Whitespace is collapsed, attributes on known tags are ignored, and '
            . 'the parser never emits block structure — for headings, lists and '
            . 'tables you compose nodes, as this page does.',
            new StylePatch(fontSizePt: 9.0, color: Color::gray(115), spaceBeforePt: 6.0),
        );
    })
    ->save(__DIR__ . '/html.pdf');

echo 'Wrote ' . __DIR__ . "/html.pdf\n";
