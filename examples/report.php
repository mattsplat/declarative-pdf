<?php

declare(strict_types=1);

require __DIR__ . '/../vendor/autoload.php';

use Pdf\Color\Color;
use Pdf\Document;
use Pdf\Geometry\Edges;
use Pdf\Layout\PageContext;
use Pdf\Node\Paragraph;
use Pdf\Style\Border;
use Pdf\Style\StylePatch;
use Pdf\Style\TextAlign;

Document::create()
    ->meta(fn ($m) => $m->title('Phase 2 Report')->author('declarative-pdf'))
    ->page(function ($p) {
        $p->header(fn (PageContext $c) => new Paragraph(
            "Phase 2 Report",
            new StylePatch(fontSizePt: 9.0, color: Color::gray(120), spaceAfterPt: 0.0),
        ));
        $p->footer(fn (PageContext $c) => new Paragraph(
            "Page {$c->pageNumber} of {$c->pageCount}",
            new StylePatch(fontSizePt: 9.0, color: Color::gray(120), align: TextAlign::Center, spaceAfterPt: 0.0),
        ));

        $p->heading(1, 'Multi-page pagination');
        for ($i = 1; $i <= 6; $i++) {
            $p->heading(3, "Section {$i}");
            $p->paragraph(str_repeat(
                "This is a body paragraph that exists only to consume vertical space so the "
                . "document flows onto several pages. Sentence {$i}. ",
                6,
            ), new StylePatch(align: TextAlign::Justify));
        }

        $p->heading(2, 'A callout box');
        $p->container([
            new Paragraph('This container has padding, a border and a tinted background, and it '
                . 'splits cleanly if it happens to straddle a page boundary.'),
        ], new StylePatch(
            paddingPt: Edges::all(8.0),
            border: Border::uniform(0.75, Color::gray(160)),
            background: Color::rgb(245, 245, 250),
            spaceBeforePt: 8.0,
            spaceAfterPt: 8.0,
        ));

        $p->heading(2, 'Lists');
        $p->bulletList([
            'First bullet point.',
            'Second bullet point, long enough to wrap onto a second line so you can check '
                . 'that the continuation lines align under the text and not the marker.',
            'Third bullet point.',
        ]);
        $p->orderedList([
            'Step one.',
            'Step two.',
            'Step three.',
        ]);

        $p->pageBreak();
        $p->heading(2, 'After an explicit page break');
        $p->paragraph('This heading was forced onto a fresh page by a PageBreak node.');
    })
    ->save(__DIR__ . '/report.pdf');

echo "Wrote " . __DIR__ . "/report.pdf\n";
