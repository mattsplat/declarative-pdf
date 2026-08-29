<?php

declare(strict_types=1);

require __DIR__ . '/../vendor/autoload.php';

use Pdf\Color\Color;
use Pdf\Document;
use Pdf\Geometry\Edges;
use Pdf\Geometry\Unit;
use Pdf\Node\ImageBlock;
use Pdf\Node\Paragraph;
use Pdf\Style\Border;
use Pdf\Style\StylePatch;
use Pdf\Style\TextAlign;
use Pdf\Text\InlineSequence;

/*
 * Images, links and columns.
 *
 *   - PNG (with alpha), JPEG and GIF, block and inline
 *   - a captioned figure inside a bordered container
 *   - internal anchors + a little table of contents
 *   - a three-column reference section
 */

$fixtures = dirname(__DIR__) . '/tests/fixtures';
$ink = Color::rgb(24, 28, 36);
$muted = Color::gray(115);

$caption = static fn (string $text): Paragraph => new Paragraph(
    $text,
    new StylePatch(fontSizePt: 8.5, italic: true, color: $muted, align: TextAlign::Center, spaceBeforePt: 3.0),
);

Document::create()
    ->meta(fn ($m) => $m->title('Media and navigation'))
    ->page(function ($p) use ($fixtures, $ink, $muted, $caption): void {
        $p->heading(1, 'Media & navigation', new StylePatch(color: $ink));

        $p->paragraph(
            InlineSequence::of('On this page: ')
                ->withLink('images', '#images')
                ->withRun(', ')
                ->withLink('inline media', '#inline')
                ->withRun(', ')
                ->withLink('links', '#links')
                ->withRun(' and ')
                ->withLink('a reference grid', '#reference')
                ->withRun('.'),
            new StylePatch(color: $muted, spaceAfterPt: 12.0),
        );

        $p->anchor('images');
        $p->heading(2, 'Images');
        $p->paragraph('Raster images are decoded, embedded once, and scaled to the '
            . 'width or height you give (aspect ratio preserved when only one is set).');

        $p->container([
            new Paragraph('A transparent PNG over a tinted panel:', new StylePatch(spaceAfterPt: 4.0)),
            ImageBlock::of("{$fixtures}/dot-rgba.png", 40.0, null, Unit::Mm, TextAlign::Center),
            $caption('dot-rgba.png — 8-bit RGBA, alpha preserved via an SMask'),
        ], new StylePatch(
            paddingPt: Edges::all(10.0),
            border: Border::uniform(0.5, Color::gray(200)),
            background: Color::rgb(247, 244, 240),
            spaceBeforePt: 8.0,
            spaceAfterPt: 10.0,
        ));

        $p->columns([
            new Paragraph('A JPEG at its natural 96 dpi size:', new StylePatch(spaceAfterPt: 4.0)),
            ImageBlock::of("{$fixtures}/bar.jpg"),
        ], count: 2);
        $p->image("{$fixtures}/square.gif", width: 18.0);

        $p->anchor('inline');
        $p->heading(2, 'Inline media');
        $p->paragraph(
            InlineSequence::of('An image can flow inline with the text ')
                ->withImage("{$fixtures}/square.gif", width: 3.5)
                ->withRun(' sitting on the baseline: it joins line breaking and '
                    . 'lifts the line height when it is taller than the type around it.'),
            new StylePatch(lineHeight: 1.6, spaceAfterPt: 12.0),
        );

        $p->anchor('links');
        $p->heading(2, 'Links');
        $p->paragraph(
            InlineSequence::of('Links are rectangles in the page annotations. This one is ')
                ->withLink('external', 'https://github.com/mattsplat/declarative-pdf')
                ->withRun('; this one jumps ')
                ->withLink('back to Images', '#images')
                ->withRun('. Internal targets resolve through the same anchor map '
                    . 'the bookmark outline uses.'),
            new StylePatch(spaceAfterPt: 12.0),
        );

        $p->anchor('reference');
        $p->heading(2, 'Reference grid');
        $p->paragraph('Three columns of short reference text, balanced by the '
            . 'column layout:', new StylePatch(spaceAfterPt: 4.0));
        $p->columns([
            new Paragraph('PNG — 8/16-bit, greyscale or RGB, palette, alpha or a '
                . 'tRNS key. Interlaced images are de-interlaced on load.'),
            new Paragraph('JPEG — baseline and progressive, YCbCr or CMYK. The '
                . 'encoded bytes are passed straight through with a DCTDecode filter.'),
            new Paragraph('GIF — first frame only, palette expanded, a transparent '
                . 'index carried as an SMask.'),
        ], count: 3, gutterPt: 12.0);
    })
    ->save(__DIR__ . '/media.pdf');

echo 'Wrote ' . __DIR__ . "/media.pdf\n";
