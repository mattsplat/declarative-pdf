<?php

declare(strict_types=1);

namespace Pdf\Render;

/**
 * PDF string formatting helpers.
 *
 * Ports `_escape()` (fpdf.php:1257), `_textstring()` (fpdf.php:1266),
 * `_isascii()` (fpdf.php:1178) and `_UTF8toUTF16()` (fpdf.php:1222). The
 * hand-rolled UTF-16 byte loop is replaced with mb_convert_encoding while
 * keeping the leading `\xFE\xFF` BOM behaviour identical.
 */
final class PdfString
{
    public static function isAscii(string $s): bool
    {
        return !preg_match('/[\x80-\xFF]/', $s);
    }

    /** Escape `(`, `)`, `\` and CR inside a literal string. */
    public static function escape(string $s): string
    {
        if (
            str_contains($s, '(') || str_contains($s, ')')
            || str_contains($s, '\\') || str_contains($s, "\r")
        ) {
            return str_replace(
                ['\\', '(', ')', "\r"],
                ['\\\\', '\\(', '\\)', '\\r'],
                $s,
            );
        }

        return $s;
    }

    /** UTF-8 to UTF-16BE with a byte-order mark. */
    public static function utf16be(string $s): string
    {
        return "\xFE\xFF" . mb_convert_encoding($s, 'UTF-16BE', 'UTF-8');
    }

    /** Format a value as a PDF literal string, `(...)`. */
    public static function text(string $s): string
    {
        if (!self::isAscii($s)) {
            $s = self::utf16be($s);
        }

        return '(' . self::escape($s) . ')';
    }
}
