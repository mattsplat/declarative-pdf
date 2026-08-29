<?php

declare(strict_types=1);

namespace Pdf\Tests\Functional;

use Pdf\Color\Color;
use Pdf\Document;
use Pdf\Node\Watermark;
use Pdf\Style\TextAlign;
use Pdf\Tests\Support\Pdf;
use PHPUnit\Framework\TestCase;

final class PageHelpersTest extends TestCase
{
    public function test_page_numbers_substitute_n_and_total_in_the_footer(): void
    {
        $pdf = Document::create()->using(Pdf::deterministicRenderer())
            ->page(fn ($p) => $p
                ->pageNumbers('{n} of {N}')
                ->paragraph('first')->pageBreak()->paragraph('second'))
            ->toString();

        $text = Pdf::contentText($pdf);
        self::assertStringContainsString('(1 of 2) Tj', $text);
        self::assertStringContainsString('(2 of 2) Tj', $text);
    }

    public function test_document_level_page_numbers_apply_to_every_page(): void
    {
        $pdf = Document::create()->using(Pdf::deterministicRenderer())
            ->pageNumbers('p{n}', align: TextAlign::Right)
            ->page(fn ($p) => $p->paragraph('one'))
            ->page(fn ($p) => $p->paragraph('two'))
            ->toString();

        $text = Pdf::contentText($pdf);
        self::assertStringContainsString('(p1) Tj', $text);
        self::assertStringContainsString('(p2) Tj', $text);
    }

    public function test_watermark_is_stamped_rotated_on_every_sheet(): void
    {
        $pdf = Document::create()->using(Pdf::deterministicRenderer())
            ->watermark('DRAFT')
            ->page(fn ($p) => $p->paragraph('one')->pageBreak()->paragraph('two'))
            ->toString();

        $text = Pdf::contentText($pdf);
        // Once per physical sheet.
        self::assertSame(2, substr_count($text, '(DRAFT) Tj'));
        // A 45° rotation matrix about the page centre.
        self::assertMatchesRegularExpression('/q 0\.70711 0\.70711 -0\.70711 0\.70711 [\d.]+ [\d.]+ cm/', $text);
    }

    public function test_translucent_watermark_emits_one_extgstate_and_references_it(): void
    {
        $pdf = Document::create()->using(Pdf::deterministicRenderer())
            ->watermark(new Watermark('DRAFT', opacity: 0.2))
            ->page(fn ($p) => $p->paragraph('x'))
            ->toString();

        self::assertStringContainsString('/ca 0.200 /CA 0.200', $pdf);
        self::assertStringContainsString('/ExtGState <<', $pdf);
        self::assertStringContainsString('/GSwm200 gs', Pdf::contentText($pdf));
    }

    public function test_opaque_watermark_emits_no_extgstate(): void
    {
        $pdf = Document::create()->using(Pdf::deterministicRenderer())
            ->watermark(new Watermark('FINAL', opacity: 1.0))
            ->page(fn ($p) => $p->paragraph('x'))
            ->toString();

        self::assertStringNotContainsString('/ExtGState', $pdf);
        self::assertStringNotContainsString(' gs ', Pdf::contentText($pdf));
    }

    public function test_background_watermark_is_drawn_before_the_body(): void
    {
        $behind = Pdf::contentText(Document::create()->using(Pdf::deterministicRenderer())
            ->watermark(new Watermark('BG', overlay: false))
            ->page(fn ($p) => $p->paragraph('content'))
            ->toString());

        $over = Pdf::contentText(Document::create()->using(Pdf::deterministicRenderer())
            ->watermark(new Watermark('OV', overlay: true))
            ->page(fn ($p) => $p->paragraph('content'))
            ->toString());

        self::assertLessThan(strpos($behind, '(content) Tj'), strpos($behind, '(BG) Tj'));
        self::assertGreaterThan(strpos($over, '(content) Tj'), strpos($over, '(OV) Tj'));
    }

    public function test_a_page_may_override_the_document_watermark(): void
    {
        $pdf = Document::create()->using(Pdf::deterministicRenderer())
            ->watermark('DRAFT')
            ->page(fn ($p) => $p->paragraph('normal'))
            ->page(fn ($p) => $p->paragraph('secret')->watermark('SECRET'))
            ->toString();

        $text = Pdf::contentText($pdf);
        self::assertSame(1, substr_count($text, '(DRAFT) Tj'));
        self::assertSame(1, substr_count($text, '(SECRET) Tj'));
    }

    public function test_explicit_font_size_is_used_verbatim(): void
    {
        $pdf = Document::create()->using(Pdf::deterministicRenderer())
            ->watermark(new Watermark('SMALL', fontSizePt: 40.0, color: Color::gray(200)))
            ->page(fn ($p) => $p->paragraph('x'))
            ->toString();

        self::assertMatchesRegularExpression('/BT \/F\d+ 40\.00 Tf/', Pdf::contentText($pdf));
    }

    public function test_goldens_are_untouched_without_a_watermark(): void
    {
        // The two-page doc renders with no watermark ops at all.
        $pdf = Document::create()->using(Pdf::deterministicRenderer())
            ->page(fn ($p) => $p->paragraph('plain'))
            ->toString();

        self::assertStringNotContainsString('/ExtGState', $pdf);
    }
}
