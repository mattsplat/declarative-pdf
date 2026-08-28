<?php

declare(strict_types=1);

namespace Pdf\Tests\Functional;

use Pdf\Document;
use Pdf\Font\FontFace;
use Pdf\Tests\Support\Fonts;
use Pdf\Tests\Support\Pdf;
use PHPUnit\Framework\TestCase;

final class TextEncodingTest extends TestCase
{
    public function test_non_ascii_text_is_emitted_as_cp1252_bytes(): void
    {
        $pdf = Document::create()
            ->using(Pdf::deterministicRenderer())
            ->page(fn ($p) => $p->paragraph("Café — €5 for a résumé"))
            ->toString();

        $content = Pdf::contentText($pdf);

        // The literal string must contain the single-byte cp1252 encodings,
        // not the raw two/three-byte UTF-8 sequences.
        self::assertMatchesRegularExpression('/\(Caf\xE9 \x97 \x805/', $content);
        self::assertStringNotContainsString("\xC3\xA9", $content, 'no raw UTF-8 e-acute');
        self::assertStringNotContainsString("\xE2\x82\xAC", $content, 'no raw UTF-8 euro sign');
    }

    public function test_string_width_uses_the_encoded_bytes(): void
    {
        $metrics = Fonts::registry()->use('Helvetica', FontFace::regular())->metrics;

        // "é" in cp1252 is one byte 0xE9; Helvetica advance for 0xE9 is 556.
        $encoded = \Pdf\Text\Encoding::forFont('é', 'cp1252');
        self::assertEqualsWithDelta(556 * 12.0 / 1000, $metrics->stringWidth($encoded, 12.0), 1e-9);
    }

    public function test_unrepresentable_characters_do_not_break_the_pdf(): void
    {
        $pdf = Document::create()
            ->using(Pdf::deterministicRenderer())
            ->page(fn ($p) => $p->paragraph('Chinese: 中文 mixed with latin'))
            ->toString();

        self::assertStringStartsWith('%PDF-', $pdf);
        self::assertStringEndsWith("%%EOF\n", $pdf);
        self::assertStringContainsString('mixed with latin', Pdf::contentText($pdf));
    }
}
