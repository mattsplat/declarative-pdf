<?php

declare(strict_types=1);

namespace Pdf\Tests\Functional;

use Pdf\Document;
use Pdf\Tests\Support\Golden;
use Pdf\Tests\Support\Pdf;
use Pdf\Text\InlineSequence;
use PHPUnit\Framework\TestCase;

final class InlineImageRenderTest extends TestCase
{
    private function fixture(string $name): string
    {
        return dirname(__DIR__) . '/fixtures/' . $name;
    }

    public function test_inline_image_is_drawn_between_words(): void
    {
        $pdf = Document::create()
            ->using(Pdf::deterministicRenderer())
            ->page(fn ($p) => $p->paragraph(
                InlineSequence::of('A small icon ')
                    ->withImage($this->fixture('square.gif'), width: 4.0)
                    ->withRun(' sits on the text baseline.'),
            ))
            ->toString();

        $content = Pdf::contentText($pdf);
        // The image XObject is drawn (cm ... /I1 Do) between the two Tj calls.
        self::assertMatchesRegularExpression('/\(A small icon \) Tj.*cm \/I1 Do Q.*\( sits on the text baseline\.\) Tj/s', $content);
        self::assertStringContainsString('/XObject <<', $pdf);
        self::assertStringContainsString('/I1 ', $pdf);
    }

    public function test_inline_image_participates_in_wrapping(): void
    {
        $pdf = Document::create()
            ->using(Pdf::deterministicRenderer())
            ->page(fn ($p) => $p->paragraph(
                InlineSequence::of('word ')
                    ->withImage($this->fixture('bar.jpg'), width: 30.0)
                    ->withRun(' and then a good deal more text that must wrap onto a second and '
                        . 'probably a third line within the A4 content width available here'),
            ))
            ->toString();

        $content = Pdf::contentText($pdf);
        self::assertSame(1, preg_match_all('/cm \/I1 Do Q/', $content), 'image drawn exactly once');
        // Multiple text lines -> multiple Td-positioned Tj.
        self::assertGreaterThan(2, preg_match_all('/\) Tj/', $content));
    }

    public function test_inline_image_document_is_byte_stable(): void
    {
        $pdf = Document::create()
            ->using(Pdf::deterministicRenderer())
            ->meta(fn ($m) => $m->title('Inline image'))
            ->page(fn ($p) => $p
                ->heading(2, 'Inline images')
                ->paragraph(
                    InlineSequence::of('Text can contain an inline image ')
                        ->withImage($this->fixture('dot-rgba.png'), width: 5.0)
                        ->withRun(' that flows with the words and wraps like any other token, '
                            . 'growing the line height when it is taller than the surrounding text.'),
                ))
            ->toString();

        Golden::assert('inline-image.pdf', $pdf);
    }
}
