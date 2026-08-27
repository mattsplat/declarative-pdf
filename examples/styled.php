<?php

declare(strict_types=1);

require __DIR__ . '/../vendor/autoload.php';

use Pdf\Color\Color;
use Pdf\Document;
use Pdf\Style\StylePatch;
use Pdf\Style\Stylesheet;
use Pdf\Text\InlineSequence;

// A house style applied document-wide, plus inline decorations.
$style = (new Stylesheet())
    ->heading(1, new StylePatch(color: Color::rgb(30, 60, 120), fontSizePt: 26.0))
    ->heading(2, new StylePatch(color: Color::rgb(30, 60, 120)))
    ->paragraph(new StylePatch(lineHeight: 1.45, spaceAfterPt: 8.0));

Document::create()
    ->meta(fn ($m) => $m->title('Styled document'))
    ->stylesheet($style)
    ->page(fn ($p) => $p
        ->heading(1, 'Chemistry notes')
        ->heading(2, 'Formulae')
        ->paragraph(
            InlineSequence::of('Water is H')
                ->withSubscript('2')
                ->withRun('O; carbon dioxide is CO')
                ->withSubscript('2')
                ->withRun(". Einstein\u{2019}s mass\u{2013}energy relation is E = mc")
                ->withSuperscript('2')
                ->withRun('.'),
        )
        ->heading(2, 'Emphasis')
        ->paragraph(
            InlineSequence::of('You can combine ')
                ->withBold('bold')
                ->withRun(', ')
                ->withItalic('italic')
                ->withRun(', ')
                ->withUnderline('underline')
                ->withRun(' and ')
                ->withStrikethrough('strikethrough')
                ->withRun(' in one run of text, and break a line')
                ->withBreak()
                ->withRun('without starting a new paragraph.'),
        ))
    ->save(__DIR__ . '/styled.pdf');

echo "Wrote " . __DIR__ . "/styled.pdf\n";
