<?php

declare(strict_types=1);

namespace Pdf\Tests\Functional;

use Pdf\Document;
use Pdf\Font\FontRepository;
use Pdf\Font\FontStyle;
use Pdf\Render\DocumentRenderer;
use Pdf\Style\StylePatch;
use Pdf\Support\FixedClock;
use Pdf\Tests\Support\Golden;
use PHPUnit\Framework\TestCase;

/**
 * Embedding an OpenType font with PostScript (CFF) outlines: a `/Type1` font
 * dict whose program is the bare `CFF ` table, `/FontFile3 /Type1C`.
 */
final class OpenTypeCffFontTest extends TestCase
{
    /** Sum of the fixture's `hmtx` advances for 'Hamburgefonstiv', units/1000em. */
    private const HAMBURGEFONSTIV = 7738;

    private function fonts(): FontRepository
    {
        $fonts = FontRepository::withBundledFonts();
        $fonts->register(
            'IBMPlexSans',
            FontStyle::Regular,
            dirname(__DIR__) . '/fixtures/IBMPlexSans-Regular.json',
        );

        return $fonts;
    }

    private function render(): string
    {
        $renderer = new DocumentRenderer(
            fontRepository: $this->fonts(),
            clock: FixedClock::at('2026-08-26T12:00:00+00:00'),
            compress: false,
            producer: 'fpdf/pdf-test',
        );

        return Document::create()
            ->using($renderer)
            ->page(fn ($p) => $p
                // Only the Regular cut is registered, so nothing here may resolve to bold.
                ->paragraph(
                    'OpenType CFF',
                    new StylePatch(fontFamily: 'IBMPlexSans', fontSizePt: 18.0),
                )
                ->paragraph(
                    'Hamburgefonstiv',
                    new StylePatch(fontFamily: 'IBMPlexSans', fontSizePt: 24.0),
                )
                ->paragraph(
                    'Grüße, façade, «quotes» — WinAnsi round-trips through the CFF charset.',
                    new StylePatch(fontFamily: 'IBMPlexSans'),
                ))
            ->toString();
    }

    public function test_embeds_the_cff_program_as_fontfile3(): void
    {
        $pdf = $this->render();

        self::assertStringContainsString('/BaseFont /IBMPlexSans', $pdf);
        self::assertStringContainsString('/Subtype /Type1', $pdf);
        self::assertStringNotContainsString('/Subtype /TrueType', $pdf);
        self::assertStringContainsString('/Subtype /Type1C', $pdf);
        self::assertStringContainsString('/Filter /FlateDecode /Subtype /Type1C', $pdf);
        self::assertMatchesRegularExpression('/\/FontFile3 \d+ 0 R/', $pdf);

        // No subset prefix (v1 embeds the whole font) and no Type1 segment lengths.
        self::assertStringNotContainsString('AAAAAA+', $pdf);
        self::assertStringNotContainsString('/Length1', $pdf);
        self::assertStringNotContainsString('/Length2', $pdf);

        self::assertStringContainsString('/FirstChar 32 /LastChar 255', $pdf);
        self::assertStringContainsString('/Encoding /WinAnsiEncoding', $pdf);
        self::assertMatchesRegularExpression('/\/ToUnicode \d+ 0 R/', $pdf);
    }

    public function test_the_program_stream_is_the_compressed_cff_table(): void
    {
        $pdf = $this->render();
        $program = (string) file_get_contents(dirname(__DIR__) . '/fixtures/IBMPlexSans-Regular.cff.z');

        self::assertSame(1, preg_match('/<<\/Length (\d+) \/Filter \/FlateDecode \/Subtype \/Type1C>>/', $pdf, $match));
        self::assertSame(strlen($program), (int) $match[1]);
        self::assertStringContainsString($program, $pdf, 'the .z file is embedded byte for byte');
    }

    public function test_widths_come_from_the_font_definition(): void
    {
        $metrics = $this->fonts()->resolve('IBMPlexSans', FontStyle::Regular)->metrics();

        self::assertEqualsWithDelta(
            self::HAMBURGEFONSTIV * 24.0 / 1000,
            $metrics->stringWidth('Hamburgefonstiv', 24.0),
            1e-9,
        );

        // /Widths carries the same advances, indexed from byte 32.
        $pdf = $this->render();
        self::assertSame(1, preg_match('/\[236 (\d+) /', $pdf, $match), 'the space advance opens the /Widths array');
        self::assertSame(284, (int) $match[1], "the '!' advance follows it");
    }

    public function test_matches_the_golden_document(): void
    {
        Golden::assert('otf-embed.pdf', $this->render());
    }
}
