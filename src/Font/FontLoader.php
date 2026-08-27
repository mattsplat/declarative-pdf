<?php

declare(strict_types=1);

namespace Pdf\Font;

use Pdf\Exception\FontException;

/**
 * Reads a JSON font definition file into a {@see FontDefinition}.
 *
 * Ports `_loadjsonfont()` (fpdf.php:1145): `enc` is lower-cased, `subsetted`
 * defaults to false. Unlike FPDF the `cw` array is kept indexed by byte value
 * (0-255) rather than remapped to `chr()` keys.
 *
 * Also closes the code-review gap where FPDF only checked for `name`: here
 * `cw` is required and must contain exactly 256 entries, otherwise a clean
 * exception is raised instead of a corrupt `/Widths` array downstream.
 */
final class FontLoader
{
    public function load(string $path): FontDefinition
    {
        if (!is_file($path) || !is_readable($path)) {
            throw new FontException('Font definition not found: ' . $path);
        }

        $json = (string) file_get_contents($path);
        if (!json_validate($json)) {
            throw new FontException('Font definition is not valid JSON: ' . $path);
        }

        /** @var array<string, mixed> $data */
        $data = json_decode($json, true, flags: JSON_THROW_ON_ERROR);

        return $this->fromArray($data, $path, dirname($path));
    }

    /** @param array<string, mixed> $data */
    public function fromArray(array $data, string $source = '<array>', ?string $sourceDirectory = null): FontDefinition
    {
        foreach (['type', 'name', 'cw'] as $key) {
            if (!isset($data[$key])) {
                throw new FontException("Font definition {$source} is missing '{$key}'.");
            }
        }

        $widths = $data['cw'];
        if (!is_array($widths) || count($widths) !== 256) {
            throw new FontException("Font definition {$source} has an invalid 'cw' array (expected 256 entries).");
        }

        /** @var array<int, int> $charWidths */
        $charWidths = [];
        foreach ($widths as $index => $width) {
            $charWidths[(int) $index] = (int) $width;
        }

        $encoding = isset($data['enc']) ? strtolower((string) $data['enc']) : null;

        return new FontDefinition(
            type: (string) $data['type'],
            name: (string) $data['name'],
            underlinePosition: (int) ($data['up'] ?? -100),
            underlineThickness: (int) ($data['ut'] ?? 50),
            charWidths: $charWidths,
            encoding: $encoding,
            unicodeMap: isset($data['uv']) && is_array($data['uv']) ? $data['uv'] : [],
            descriptor: isset($data['desc']) && is_array($data['desc']) ? $data['desc'] : [],
            differences: isset($data['diff']) ? (string) $data['diff'] : null,
            file: isset($data['file']) ? (string) $data['file'] : null,
            originalSize: isset($data['originalsize']) ? (int) $data['originalsize'] : null,
            size1: isset($data['size1']) ? (int) $data['size1'] : null,
            size2: isset($data['size2']) ? (int) $data['size2'] : null,
            subsetted: (bool) ($data['subsetted'] ?? false),
            sourceDirectory: $sourceDirectory,
        );
    }
}
