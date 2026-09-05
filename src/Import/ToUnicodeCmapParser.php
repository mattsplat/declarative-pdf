<?php

declare(strict_types=1);

namespace Pdf\Import;

/**
 * Parses a `/ToUnicode` CMap stream — the reverse of {@see \Pdf\Font\ToUnicodeCMap}
 * — into a character-code → UTF-8 text map, for text extraction.
 *
 * A ToUnicode CMap is a small PostScript program. Rather than a general
 * PostScript tokenizer, this isolates the two constructs that matter
 * (`beginbfchar…endbfchar`, `beginbfrange…endbfrange` — what every real-world
 * generator, including this library's own {@see \Pdf\Font\ToUnicodeCMap},
 * actually emits) and reads each with {@see PdfParser}, whose `<hex>` and
 * `[…]` grammar happens to be exactly what those blocks contain. Everything
 * else in the program (`begincmap`, `def`, `findresource`, …) is ignored.
 */
final class ToUnicodeCmapParser
{
    /** @return array<int, string> character code => decoded UTF-8 text */
    public static function parse(string $cmap): array
    {
        $map = [];

        if (preg_match_all('/beginbfchar(.*?)endbfchar/s', $cmap, $blocks)) {
            foreach ($blocks[1] as $block) {
                self::parseCharBlock($block, $map);
            }
        }

        if (preg_match_all('/beginbfrange(.*?)endbfrange/s', $cmap, $blocks)) {
            foreach ($blocks[1] as $block) {
                self::parseRangeBlock($block, $map);
            }
        }

        return $map;
    }

    /** @param array<int, string> $map */
    private static function parseCharBlock(string $block, array &$map): void
    {
        $parser = new PdfParser($block);
        $length = strlen($block);

        while (true) {
            $parser->skipWhitespace();
            if ($parser->position() >= $length) {
                return;
            }
            $src = self::nextHexBytes($parser);
            $parser->skipWhitespace();
            $dst = $src !== null ? self::nextHexBytes($parser) : null;
            if ($src === null || $dst === null) {
                return;
            }
            $map[self::codeOf($src)] = self::utf16beToUtf8($dst);
        }
    }

    /** @param array<int, string> $map */
    private static function parseRangeBlock(string $block, array &$map): void
    {
        $parser = new PdfParser($block);
        $length = strlen($block);

        while (true) {
            $parser->skipWhitespace();
            if ($parser->position() >= $length) {
                return;
            }
            $lo = self::nextHexBytes($parser);
            $parser->skipWhitespace();
            $hi = $lo !== null ? self::nextHexBytes($parser) : null;
            $parser->skipWhitespace();
            if ($lo === null || $hi === null || $parser->position() >= $length) {
                return;
            }

            $loCode = self::codeOf($lo);
            $count = self::codeOf($hi) - $loCode + 1;
            if ($count < 1 || $count > 65536) {
                return;
            }

            if ($block[$parser->position()] === '[') {
                self::applyExplicitDestinations($parser->parseValue(), $loCode, $map);
                continue;
            }

            $dst = self::nextHexBytes($parser);
            if ($dst === null) {
                return;
            }
            self::applyIncrementingDestination($dst, $loCode, $count, $map);
        }
    }

    /** @param array<int, string> $map */
    private static function applyExplicitDestinations(mixed $items, int $loCode, array &$map): void
    {
        if (!is_array($items)) {
            return;
        }
        foreach ($items as $offset => $item) {
            if (is_string($item)) {
                $map[$loCode + $offset] = self::utf16beToUtf8($item);
            }
        }
    }

    /** @param array<int, string> $map */
    private static function applyIncrementingDestination(string $dst, int $loCode, int $count, array &$map): void
    {
        if (strlen($dst) !== 2) {
            // A multi-unit (ligature / astral) destination for a whole range is
            // vanishingly rare; approximate by repeating the same text.
            $text = self::utf16beToUtf8($dst);
            for ($i = 0; $i < $count; $i++) {
                $map[$loCode + $i] = $text;
            }

            return;
        }

        $base = self::codeOf($dst);
        for ($i = 0; $i < $count; $i++) {
            $map[$loCode + $i] = self::utf16beToUtf8(pack('n', ($base + $i) & 0xFFFF));
        }
    }

    private static function nextHexBytes(PdfParser $parser): ?string
    {
        $value = $parser->parseValue();

        return is_string($value) ? $value : null;
    }

    private static function codeOf(string $bytes): int
    {
        $code = 0;
        for ($i = 0; $i < strlen($bytes); $i++) {
            $code = ($code << 8) | ord($bytes[$i]);
        }

        return $code;
    }

    private static function utf16beToUtf8(string $bytes): string
    {
        if ($bytes === '') {
            return '';
        }
        if (strlen($bytes) % 2 === 1) {
            $bytes .= "\x00";
        }

        return mb_convert_encoding($bytes, 'UTF-8', 'UTF-16BE');
    }
}
