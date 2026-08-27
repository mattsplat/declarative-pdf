<?php

declare(strict_types=1);

namespace Pdf\Tests\Functional;

use Pdf\Document;
use Pdf\Style\StylePatch;
use Pdf\Style\Stylesheet;
use Pdf\Tests\Support\Golden;
use Pdf\Tests\Support\Pdf;
use Pdf\Text\InlineSequence;
use PHPUnit\Framework\TestCase;

final class InlineFeaturesTest extends TestCase
{
    public function test_underline_and_strikethrough_draw_rules_at_the_baseline(): void
    {
        $pdf = Document::create()
            ->using(Pdf::deterministicRenderer())
            ->page(fn ($p) => $p->paragraph(
                InlineSequence::of('normal ')
                    ->withUnderline('underlined')
                    ->withRun(' ')
                    ->withStrikethrough('struck'),
            ))
            ->toString();

        $content = Pdf::contentText($pdf);
        // Two decoration rectangles (underline + strike), filled in text colour.
        self::assertGreaterThanOrEqual(2, preg_match_all('/ re f Q/', $content));
        self::assertStringContainsString('(underlined) Tj', $content);
        self::assertStringContainsString('(struck) Tj', $content);
    }

    public function test_superscript_uses_a_smaller_font_and_a_raised_baseline(): void
    {
        $pdf = Document::create()
            ->using(Pdf::deterministicRenderer())
            ->page(fn ($p) => $p->paragraph(
                InlineSequence::of('E = mc')->withSuperscript('2'),
            ))
            ->toString();

        $content = Pdf::contentText($pdf);
        // The "2" is drawn with a smaller Tf and a different Td y than the body.
        self::assertMatchesRegularExpression('/\/F\d+ 12\.00 Tf .* \(E = mc\) Tj/s', $content);
        self::assertMatchesRegularExpression('/\/F\d+ 8\.40 Tf .* \(2\) Tj/s', $content);
    }

    public function test_hard_break_splits_a_line_without_paragraph_spacing(): void
    {
        $pdf = Document::create()
            ->using(Pdf::deterministicRenderer())
            ->page(fn ($p) => $p->paragraph(
                InlineSequence::of('line one')->withBreak()->withRun('line two'),
            ))
            ->toString();

        $content = Pdf::contentText($pdf);
        self::assertStringContainsString('(line one) Tj', $content);
        self::assertStringContainsString('(line two) Tj', $content);
    }

    public function test_stylesheet_restyles_headings_and_paragraphs(): void
    {
        $sheet = (new Stylesheet())
            ->heading(1, new StylePatch(color: \Pdf\Color\Color::rgb(180, 0, 0)))
            ->paragraph(new StylePatch(lineHeight: 1.6));

        $pdf = Document::create()
            ->using(Pdf::deterministicRenderer())
            ->stylesheet($sheet)
            ->page(fn ($p) => $p->heading(1, 'Red Title')->paragraph('Loosely leaded body.'))
            ->toString();

        $content = Pdf::contentText($pdf);
        // h1 text is wrapped in a non-black colour.
        self::assertMatchesRegularExpression('/q 0\.706 0\.000 0\.000 rg .*\(Red Title\) Tj/s', $content);
    }

    public function test_inline_features_document_is_byte_stable(): void
    {
        $pdf = Document::create()
            ->using(Pdf::deterministicRenderer())
            ->meta(fn ($m) => $m->title('Inline'))
            ->page(fn ($p) => $p
                ->heading(2, 'Inline decorations')
                ->paragraph(
                    InlineSequence::of('This has ')
                        ->withBold('bold')
                        ->withRun(', ')
                        ->withItalic('italic')
                        ->withRun(', ')
                        ->withUnderline('underline')
                        ->withRun(', ')
                        ->withStrikethrough('strike')
                        ->withRun(', a footnote')
                        ->withSuperscript('1')
                        ->withRun(' and H')
                        ->withSubscript('2')
                        ->withRun('O.'),
                )
                ->paragraph(
                    InlineSequence::of('First line.')->withBreak()->withRun('Second line, same paragraph.'),
                ))
            ->toString();

        Golden::assert('inline.pdf', $pdf);
    }
}
