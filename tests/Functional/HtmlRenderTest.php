<?php

declare(strict_types=1);

namespace Pdf\Tests\Functional;

use Pdf\Document;
use Pdf\Tests\Support\Golden;
use Pdf\Tests\Support\Pdf;
use PHPUnit\Framework\TestCase;

final class HtmlRenderTest extends TestCase
{
    public function test_html_paragraph_renders_markup_and_links(): void
    {
        $pdf = Document::create()
            ->using(Pdf::deterministicRenderer())
            ->page(fn ($p) => $p->html(
                'Use <b>bold</b> and <a href="https://example.com">a link</a> and x<sup>2</sup>.',
            ))
            ->toString();

        $content = Pdf::contentText($pdf);
        self::assertStringContainsString('(bold) Tj', $content);
        self::assertStringContainsString('(a link) Tj', $content);
        self::assertStringContainsString('/A <</S /URI /URI (https://example.com)>>', $pdf);
        // superscript "2" at a reduced size
        self::assertMatchesRegularExpression('/\/F\d+ 8\.40 Tf .* \(2\) Tj/s', $content);
    }

    public function test_html_document_is_byte_stable(): void
    {
        $markup = 'You can use <b>bold</b>, <i>italic</i>, <u>underlined</u> and '
            . '<s>struck</s> text, formulae like E = mc<sup>2</sup> and H<sub>2</sub>O, '
            . 'and <a href="https://example.com">links</a>.<br>New line, same paragraph. '
            . 'Entities: &amp; &mdash;.';

        $pdf = Document::create()
            ->using(Pdf::deterministicRenderer())
            ->meta(fn ($m) => $m->title('HTML'))
            ->page(fn ($p) => $p->heading(2, 'Inline HTML')->html($markup))
            ->toString();

        Golden::assert('html.pdf', $pdf);
    }
}
