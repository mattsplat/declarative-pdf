<?php

declare(strict_types=1);

namespace Pdf\Render;

use Pdf\Import\PdfDictionary;
use Pdf\Import\PdfName;
use Pdf\Import\PdfReference;
use Pdf\Import\PdfStream;

/**
 * Serialises a parsed PDF value (from {@see \Pdf\Import\PdfParser}) back to PDF
 * syntax, remapping indirect references to new object numbers.
 */
final class PdfValueWriter
{
    /** @param array<int, int> $refMap old object number => new object number */
    public static function write(mixed $value, array $refMap): string
    {
        return match (true) {
            $value === null => 'null',
            $value === true => 'true',
            $value === false => 'false',
            is_int($value) => (string) $value,
            is_float($value) => self::number($value),
            is_string($value) => '<' . bin2hex($value) . '>',
            $value instanceof PdfName => '/' . self::escapeName($value->value),
            $value instanceof PdfReference => isset($refMap[$value->number])
                ? $refMap[$value->number] . ' 0 R'
                : 'null',
            $value instanceof PdfDictionary => self::dictionary($value->entries, $refMap),
            is_array($value) => '[' . implode(' ', array_map(
                static fn ($item) => self::write($item, $refMap),
                $value,
            )) . ']',
            $value instanceof PdfStream => self::dictionary(
                [...$value->dict, 'Length' => strlen($value->rawData)],
                $refMap,
            ),
            default => 'null',
        };
    }

    /**
     * @param array<string, mixed> $entries
     * @param array<int, int>      $refMap
     */
    public static function dictionary(array $entries, array $refMap): string
    {
        $parts = [];
        foreach ($entries as $key => $entry) {
            $parts[] = '/' . self::escapeName($key) . ' ' . self::write($entry, $refMap);
        }

        return '<<' . implode(' ', $parts) . '>>';
    }

    private static function number(float $value): string
    {
        $text = rtrim(rtrim(sprintf('%.6F', $value), '0'), '.');

        return $text === '' || $text === '-' ? '0' : $text;
    }

    private static function escapeName(string $name): string
    {
        return (string) preg_replace_callback(
            '/[^\x21-\x7E]|[#()<>\[\]{}\/%]/',
            static fn (array $m) => sprintf('#%02X', ord($m[0])),
            $name,
        );
    }
}
