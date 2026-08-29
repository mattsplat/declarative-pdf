<?php

declare(strict_types=1);

require __DIR__ . '/../vendor/autoload.php';

use Pdf\Color\Color;
use Pdf\Document;
use Pdf\Font\FontFace;
use Pdf\Font\FontRepository;
use Pdf\Render\DocumentRenderer;
use Pdf\Style\StylePatch;

/*
 * FPDF tutorial 7 — embedded fonts — as a type-specimen sheet.
 *
 * Two custom families are registered from definition files built by
 * tools/makefont/makefont.php:
 *
 *   CevicheOne   .ttf  -> TrueType outlines, embedded subsetted (/FontFile2)
 *   IBM Plex Sans .otf -> PostScript (CFF) outlines, embedded whole (/FontFile3)
 *
 * Only the Regular cut of each is registered here, so the samples stay upright.
 */

$fixtures = dirname(__DIR__) . '/tests/fixtures';

$fonts = FontRepository::withBundledFonts();
$fonts->register('CevicheOne', FontFace::regular(), __DIR__ . '/data/CevicheOne-Regular.json');
$fonts->register('IBMPlexSans', FontFace::regular(), "{$fixtures}/IBMPlexSans-Regular.json");

$ink = Color::rgb(17, 17, 17);
$muted = Color::gray(120);
$pangram = 'The quick brown fox jumps over the lazy dog';

$specimen = static function ($p, string $family, string $label, string $note) use ($ink, $muted, $pangram): void {
    $p->heading(2, $label, new StylePatch(color: $ink, spaceBeforePt: 18.0));
    $p->paragraph($note, new StylePatch(fontSizePt: 9.0, color: $muted, spaceAfterPt: 8.0));

    $p->paragraph('Aa Bb Cc', new StylePatch(fontFamily: $family, fontSizePt: 46.0, spaceAfterPt: 2.0));
    $p->paragraph($pangram, new StylePatch(fontFamily: $family, fontSizePt: 22.0, spaceAfterPt: 2.0));
    $p->paragraph($pangram, new StylePatch(fontFamily: $family, fontSizePt: 13.0, spaceAfterPt: 2.0));
    $p->paragraph('0123456789  &  @  #  $  %  ( . , ; : ! ? )', new StylePatch(
        fontFamily: $family,
        fontSizePt: 13.0,
        color: $muted,
    ));
};

Document::create()
    ->using(new DocumentRenderer($fonts))
    ->meta(fn ($m) => $m->title('Type specimen')->subject('Embedded TrueType and CFF fonts'))
    ->page(function ($p) use ($ink, $specimen): void {
        $p->heading(1, 'Embedded fonts', new StylePatch(color: $ink));
        $p->paragraph(
            'Register a definition file with FontRepository::register(); the '
            . 'family is then usable anywhere a StylePatch names it. The renderer '
            . 'embeds the program and builds the width tables.',
            new StylePatch(spaceAfterPt: 4.0),
        );

        $specimen($p, 'CevicheOne', 'CevicheOne  —  TrueType, subsetted',
            'A .ttf display face. Only the glyphs actually used are embedded (/FontFile2).');
        $specimen($p, 'IBMPlexSans', 'IBM Plex Sans  —  OpenType / CFF',
            'A .otf text face with PostScript outlines. The CFF table is lifted out '
            . 'and embedded whole (/FontFile3, /Subtype /Type1C), 256 glyphs.');

        $p->rule(0.5, Color::gray(215));
        $p->paragraph(
            'This line is the built-in Helvetica, for comparison — no embedding, '
            . 'WinAnsi encoding, metrics from the bundled AFM.',
            new StylePatch(fontSizePt: 10.0, color: Color::gray(120), spaceBeforePt: 6.0),
        );
    })
    ->save(__DIR__ . '/custom-font.pdf');

echo 'Wrote ' . __DIR__ . "/custom-font.pdf\n";
