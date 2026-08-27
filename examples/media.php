<?php

declare(strict_types=1);

require __DIR__ . '/../vendor/autoload.php';

use Pdf\Document;
use Pdf\Node\Paragraph;
use Pdf\Style\StylePatch;
use Pdf\Style\TextAlign;
use Pdf\Text\InlineSequence;

$fixtures = dirname(__DIR__) . '/tests/fixtures';

Document::create()
    ->meta(fn ($m) => $m->title('Images, links and columns'))
    ->page(function ($p) use ($fixtures) {
        $p->heading(1, 'Media & navigation');

        $p->anchor('images');
        $p->heading(2, 'Images');
        $p->paragraph('A transparent PNG, sized to 40mm wide, centered:');
        $p->image("{$fixtures}/dot-rgba.png", width: 40.0, align: TextAlign::Center);
        $p->paragraph('A JPEG at its natural 96dpi size, and a GIF:');
        $p->image("{$fixtures}/bar.jpg");
        $p->image("{$fixtures}/square.gif", width: 20.0);

        $p->heading(2, 'Links');
        $p->paragraph(
            InlineSequence::of('This paragraph has an ')
                ->withLink('external link to example.com', 'https://example.com')
                ->withRun(' and an ')
                ->withLink('internal link back to the Images section', '#images')
                ->withRun('.'),
        );

        $p->heading(2, 'Inline images');
        $p->paragraph(
            InlineSequence::of('An image can also flow inline with the text ')
                ->withImage("{$fixtures}/square.gif", width: 4.0)
                ->withRun(' — it sits on the baseline, participates in line breaking, '
                    . 'and grows the line height when it is taller than the surrounding text.'),
        );

        $p->heading(2, 'Columns');
        $p->columns([
            new Paragraph(str_repeat('Column flow text that wraps within its narrow measure. ', 12)),
            new Paragraph('Second paragraph.', new StylePatch(align: TextAlign::Justify)),
        ], count: 2);
    })
    ->save(__DIR__ . '/media.pdf');

echo "Wrote " . __DIR__ . "/media.pdf\n";
