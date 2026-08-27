<?php

declare(strict_types=1);

namespace Pdf\Text;

/**
 * Transcodes UTF-8 API input to the single-byte encoding a core / WinAnsi font
 * actually uses (almost always Windows-1252).
 *
 * FPDF pushed this onto the caller (ISO-8859-1 input, or the `$isUTF8` flags);
 * here all text is UTF-8 and gets converted once, before measuring, so glyph
 * widths and the emitted `(...)` bytes agree. Characters absent from the target
 * encoding become `?`. Fonts with no declared encoding (Symbol, ZapfDingbats)
 * are passed through untouched.
 *
 * Windows-1252 (the overwhelming majority) goes through mbstring; every other
 * `makefont` code page (cp1250-1258, cp874, KOI8, ISO-8859-*) goes through
 * iconv, which mbstring does not fully cover.
 */
final class Encoding
{
    /** iconv target for a font-definition `enc` value. */
    private static function iconvTarget(string $fontEncoding): ?string
    {
        return match (strtolower($fontEncoding)) {
            'cp1250' => 'CP1250',
            'cp1251' => 'CP1251',
            'cp1252' => 'CP1252',
            'cp1253' => 'CP1253',
            'cp1254' => 'CP1254',
            'cp1255' => 'CP1255',
            'cp1256' => 'CP1256',
            'cp1257' => 'CP1257',
            'cp1258' => 'CP1258',
            'cp874' => 'CP874',
            'koi8-r' => 'KOI8-R',
            'koi8-u' => 'KOI8-U',
            'iso-8859-1', 'latin1' => 'ISO-8859-1',
            'iso-8859-2' => 'ISO-8859-2',
            'iso-8859-3' => 'ISO-8859-3',
            'iso-8859-4' => 'ISO-8859-4',
            'iso-8859-5' => 'ISO-8859-5',
            'iso-8859-7' => 'ISO-8859-7',
            'iso-8859-9' => 'ISO-8859-9',
            'iso-8859-11' => 'ISO-8859-11',
            'iso-8859-15' => 'ISO-8859-15',
            'iso-8859-16' => 'ISO-8859-16',
            default => null,
        };
    }

    public static function forFont(string $utf8, ?string $fontEncoding): string
    {
        if ($utf8 === '' || $fontEncoding === null) {
            return $utf8;
        }

        $target = self::iconvTarget($fontEncoding);
        if ($target === null) {
            return $utf8; // an encoding we don't know how to map — leave it
        }

        if (!mb_check_encoding($utf8, 'UTF-8')) {
            return $utf8;
        }
        if (!preg_match('/[\x80-\xFF]/', $utf8)) {
            return $utf8; // pure ASCII
        }

        // Fast, dependency-free path for the common case.
        if ($target === 'CP1252') {
            $previous = mb_substitute_character();
            mb_substitute_character(0x3F);
            try {
                return mb_convert_encoding($utf8, 'Windows-1252', 'UTF-8');
            } finally {
                mb_substitute_character($previous);
            }
        }

        if (function_exists('iconv')) {
            $converted = @iconv('UTF-8', $target . '//TRANSLIT//IGNORE', $utf8);
            if ($converted !== false) {
                return $converted;
            }
        }

        // Last resort: strip to ASCII so output is at worst lossy, never broken.
        return (string) preg_replace('/[\x80-\xFF]+/', '?', $utf8);
    }
}
