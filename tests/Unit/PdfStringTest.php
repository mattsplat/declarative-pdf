<?php

declare(strict_types=1);

namespace Pdf\Tests\Unit;

use Pdf\Render\PdfString;
use PHPUnit\Framework\TestCase;

final class PdfStringTest extends TestCase
{
    public function test_escape_only_touches_special_characters(): void
    {
        self::assertSame('plain text', PdfString::escape('plain text'));
        self::assertSame('a \\( b \\) c \\\\ d', PdfString::escape('a ( b ) c \\ d'));
        self::assertSame('line\\rbreak', PdfString::escape("line\rbreak"));
    }

    public function test_ascii_text_is_wrapped_in_parentheses(): void
    {
        self::assertSame('(Hello)', PdfString::text('Hello'));
    }

    public function test_non_ascii_text_becomes_utf16be_with_bom(): void
    {
        $out = PdfString::text('é');

        self::assertStringStartsWith('(', $out);
        self::assertStringStartsWith("\xFE\xFF", substr($out, 1));
        // U+00E9 in UTF-16BE
        self::assertStringContainsString("\x00\xE9", $out);
    }

    public function test_is_ascii(): void
    {
        self::assertTrue(PdfString::isAscii('abc123 ~'));
        self::assertFalse(PdfString::isAscii("caf\xE9"));
    }
}
