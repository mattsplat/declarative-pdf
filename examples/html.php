<?php

declare(strict_types=1);

require __DIR__ . '/../vendor/autoload.php';

use Pdf\Document;
use Pdf\Text\Html;

// The declarative answer to tuto6's WriteHTML(): inline markup only.
$markup = <<<'HTML'
You can use <b>bold</b>, <i>italic</i>, <u>underlined</u> and
<s>struck</s> text, write formulae like E = mc<sup>2</sup> and
H<sub>2</sub>O, link to <a href="https://example.com">a website</a>,<br>
and break lines without ending the paragraph. Entities such as &amp; and
&mdash; are decoded.
HTML;

Document::create()
    ->meta(fn ($m) => $m->title('Inline HTML'))
    ->page(fn ($p) => $p
        ->heading(2, 'Inline HTML')
        ->html($markup)
        ->paragraph('Or build the same thing directly:')
        ->add(new \Pdf\Node\Paragraph(
            Html::toInline('A <b>second <i>nested</i></b> example.'),
        )))
    ->save(__DIR__ . '/html.pdf');

echo "Wrote " . __DIR__ . "/html.pdf\n";
