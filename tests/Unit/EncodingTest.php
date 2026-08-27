<?php

declare(strict_types=1);

namespace Pdf\Tests\Unit;

use Pdf\Text\Encoding;
use PHPUnit\Framework\TestCase;

final class EncodingTest extends TestCase
{
    public function test_ascii_passes_through_untouched(): void
    {
        self::assertSame('Hello, world!', Encoding::forFont('Hello, world!', 'cp1252'));
    }

    public function test_latin_accents_map_to_single_cp1252_bytes(): void
    {
        $out = Encoding::forFont('Café résumé', 'cp1252');

        self::assertSame(strlen('Caf') + 1 + strlen(' r') + 1 + strlen('sum') + 1, strlen($out));
        self::assertSame("\xE9", $out[3], 'e-acute is 0xE9 in cp1252');
    }

    public function test_typographic_punctuation_maps_into_the_cp1252_high_range(): void
    {
        // U+2019 ', U+2014 —, U+20AC €
        self::assertSame("\x92", Encoding::forFont("\u{2019}", 'cp1252'));
        self::assertSame("\x97", Encoding::forFont("\u{2014}", 'cp1252'));
        self::assertSame("\x80", Encoding::forFont("\u{20AC}", 'cp1252'));
    }

    public function test_unrepresentable_characters_become_question_marks(): void
    {
        self::assertSame('??', Encoding::forFont("\u{4E2D}\u{6587}", 'cp1252'));
    }

    public function test_no_encoding_means_pass_through(): void
    {
        self::assertSame("\u{2022}", Encoding::forFont("\u{2022}", null));
    }

    public function test_global_substitute_character_is_restored(): void
    {
        $before = mb_substitute_character();
        Encoding::forFont("\u{4E2D}", 'cp1252');
        self::assertSame($before, mb_substitute_character());
    }
}
