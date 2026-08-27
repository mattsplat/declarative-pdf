<?php

declare(strict_types=1);

namespace Pdf\Tests\Functional;

use Pdf\Document;
use Pdf\Font\FontRepository;
use Pdf\Font\FontStyle;
use Pdf\Render\DocumentRenderer;
use Pdf\Style\StylePatch;
use Pdf\Support\FixedClock;
use PHPUnit\Framework\TestCase;

final class EmbeddedFontTest extends TestCase
{
    private function renderer(): DocumentRenderer
    {
        $fonts = FontRepository::withBundledFonts();
        $fonts->register(
            'CevicheOne',
            FontStyle::Regular,
            dirname(__DIR__) . '/fixtures/CevicheOne-Regular.json',
        );

        return new DocumentRenderer(
            fontRepository: $fonts,
            clock: FixedClock::at('2026-08-26T12:00:00+00:00'),
            compress: false,
            producer: 'fpdf/pdf-test',
        );
    }

    public function test_embeds_a_subsetted_truetype_font_program(): void
    {
        $pdf = Document::create()
            ->using($this->renderer())
            ->page(fn ($p) => $p->paragraph(
                'Enjoy new fonts',
                new StylePatch(fontFamily: 'CevicheOne', fontSizePt: 32.0),
            ))
            ->toString();

        // Subset name prefix, TrueType subtype, descriptor with the embedded program.
        self::assertStringContainsString('/BaseFont /AAAAAA+CevicheOne-Regular', $pdf);
        self::assertStringContainsString('/Subtype /TrueType', $pdf);
        self::assertStringContainsString('/FontFile2 ', $pdf);
        self::assertStringContainsString('/Length1 25916', $pdf, 'original (uncompressed) program size');
        self::assertStringContainsString('/FontDescriptor', $pdf);
        self::assertMatchesRegularExpression('/\/Widths \d+ 0 R/', $pdf);
    }

    public function test_embedded_and_core_fonts_coexist_with_a_shared_tounicode_cmap(): void
    {
        $pdf = Document::create()
            ->using($this->renderer())
            ->page(fn ($p) => $p
                ->paragraph('custom', new StylePatch(fontFamily: 'CevicheOne'))
                ->paragraph('helvetica'))
            ->toString();

        self::assertStringContainsString('/BaseFont /Helvetica', $pdf);
        self::assertStringContainsString('/BaseFont /AAAAAA+CevicheOne-Regular', $pdf);
        // One ToUnicode object referenced by both (both are cp1252).
        preg_match_all('/\/ToUnicode (\d+) 0 R/', $pdf, $m);
        self::assertCount(2, $m[1]);
        self::assertSame($m[1][0], $m[1][1]);
    }
}
