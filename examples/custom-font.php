<?php

declare(strict_types=1);

require __DIR__ . '/../vendor/autoload.php';

use Pdf\Document;
use Pdf\Font\FontRepository;
use Pdf\Font\FontFace;
use Pdf\Render\DocumentRenderer;
use Pdf\Style\StylePatch;

// Port of FPDF tutorial 7: register an embedded TrueType font and use it.
// The .json + .z were produced by the makefont tool from CevicheOne-Regular.ttf.
$fonts = FontRepository::withBundledFonts();
$fonts->register(
    'CevicheOne',
    FontFace::regular(),
    __DIR__ . '/data/CevicheOne-Regular.json',
);

Document::create()
    ->using(new DocumentRenderer($fonts))
    ->meta(fn ($m) => $m->title('Custom font'))
    ->page(fn ($p) => $p
        ->paragraph('Enjoy new fonts with the FPDF rewrite!', new StylePatch(
            fontFamily: 'CevicheOne',
            fontSizePt: 40.0,
        ))
        ->paragraph('The line above is drawn with an embedded, subsetted TrueType '
            . 'font program; this line is the built-in Helvetica for comparison.'))
    ->save(__DIR__ . '/custom-font.pdf');

echo "Wrote " . __DIR__ . "/custom-font.pdf\n";
