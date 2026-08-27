<?php

declare(strict_types=1);

require __DIR__ . '/../vendor/autoload.php';

use Pdf\Document;
use Pdf\Style\StylePatch;
use Pdf\Text\InlineSequence;

// Port of FPDF tutorial 1, in the declarative API.
Document::create()
    ->meta(fn ($m) => $m->title('Hello')->author('FPDF'))
    ->page(fn ($p) => $p
        ->heading(1, 'Hello World!')
        ->paragraph(
            'FPDF has been reimagined as a typed, declarative library. Instead '
            . 'of driving a cursor with AddPage/SetFont/Cell, you describe the '
            . 'document as a tree of nodes and a layout engine places them.'
        )
        ->paragraph(
            InlineSequence::of('This paragraph mixes ')
                ->withRun('bold', new StylePatch(bold: true))
                ->withRun(' and ')
                ->withRun('italic', new StylePatch(italic: true))
                ->withRun(' runs, then wraps across several lines so you can see '
                    . 'the greedy line breaker doing its job just like MultiCell did.'),
            new StylePatch(align: \Pdf\Style\TextAlign::Justify),
        ))
    ->save(__DIR__ . '/hello.pdf');

echo "Wrote " . __DIR__ . "/hello.pdf\n";
