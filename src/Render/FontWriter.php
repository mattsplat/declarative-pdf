<?php

declare(strict_types=1);

namespace Pdf\Render;

use Pdf\Exception\FontException;
use Pdf\Font\ResolvedFont;
use Pdf\Font\ToUnicodeCMap;

/**
 * Writes font objects into the PDF.
 *
 * Ported from `_putfonts()` (fpdf.php:1654-1768): font-file embedding with the
 * Type1 segment splice, `/Differences` encoding objects, ToUnicode CMap
 * objects, the Core `/Type1 /WinAnsiEncoding` branch and the Type1/TrueType
 * branch with `/Widths`, `/FontDescriptor` and `/FontFile[2]`.
 *
 * @phpstan-type FontFileInfo array{length1:int, length2?:int}
 */
final class FontWriter
{
    /** @var array<string, int> encoding name => object number */
    private array $encodings = [];

    /** @var array<string, int> cmap key => object number */
    private array $cmaps = [];

    /** @var array<string, int> font file path => object number */
    private array $fontFiles = [];

    public function __construct(private readonly PdfWriter $writer)
    {
    }

    /** @param list<ResolvedFont> $fonts */
    public function write(array $fonts): void
    {
        $this->embedFontFiles($fonts);

        foreach ($fonts as $font) {
            $definition = $font->definition;

            $encodingRef = null;
            if ($definition->differences !== null && $definition->encoding !== null) {
                $encodingRef = $this->encodings[$definition->encoding] ??= $this->writeEncoding($definition->differences);
            }

            $cmapRef = null;
            if ($definition->unicodeMap !== []) {
                $cmapKey = $definition->encoding ?? $definition->name;
                $cmapRef = $this->cmaps[$cmapKey] ??= $this->writer->streamObject(
                    ToUnicodeCMap::build($definition->unicodeMap),
                );
            }

            $name = $definition->subsetted ? 'AAAAAA+' . $definition->name : $definition->name;

            if ($definition->isCore()) {
                $font->objectNumber = $this->writeCoreFont($name, $cmapRef);
                continue;
            }

            if ($definition->type === 'Type1' || $definition->type === 'TrueType') {
                $font->objectNumber = $this->writeEmbeddableFont($font, $name, $encodingRef, $cmapRef);
                continue;
            }

            throw new FontException('Unsupported font type: ' . $definition->type);
        }
    }

    /** @param list<ResolvedFont> $fonts */
    private function embedFontFiles(array $fonts): void
    {
        foreach ($fonts as $font) {
            $path = $font->definition->fontFilePath();
            if ($path === null || isset($this->fontFiles[$path])) {
                continue;
            }

            $program = @file_get_contents($path);
            if ($program === false || $program === '') {
                throw new FontException('Font file not found: ' . $path);
            }

            $compressed = str_ends_with($path, '.z');
            $length1 = $font->definition->originalSize ?? $font->definition->size1 ?? strlen($program);
            $length2 = $font->definition->size2;

            if (!$compressed && $length2 !== null) {
                // Type1: keep only the clear and encrypted segments.
                $program = substr($program, 6, $font->definition->size1 ?? 0)
                    . substr($program, 6 + ($font->definition->size1 ?? 0) + 6, $length2);
            }

            $object = $this->writer->beginObject();
            $dict = '<</Length ' . strlen($program);
            if ($compressed) {
                $dict .= ' /Filter /FlateDecode';
            }
            $dict .= ' /Length1 ' . $length1;
            if ($length2 !== null) {
                $dict .= ' /Length2 ' . $length2 . ' /Length3 0';
            }
            $this->writer->line($dict . '>>');
            $this->writer->stream($program);
            $this->writer->endObject();

            $this->fontFiles[$path] = $object;
        }
    }

    private function writeEncoding(string $differences): int
    {
        $object = $this->writer->beginObject();
        $this->writer->line('<</Type /Encoding /BaseEncoding /WinAnsiEncoding /Differences [' . $differences . ']>>');
        $this->writer->endObject();

        return $object;
    }

    private function writeCoreFont(string $name, ?int $cmapRef): int
    {
        $object = $this->writer->beginObject();
        $this->writer->line('<</Type /Font');
        $this->writer->line('/BaseFont /' . $name);
        $this->writer->line('/Subtype /Type1');
        if ($name !== 'Symbol' && $name !== 'ZapfDingbats') {
            $this->writer->line('/Encoding /WinAnsiEncoding');
        }
        if ($cmapRef !== null) {
            $this->writer->line('/ToUnicode ' . $cmapRef . ' 0 R');
        }
        $this->writer->line('>>');
        $this->writer->endObject();

        return $object;
    }

    private function writeEmbeddableFont(ResolvedFont $font, string $name, ?int $encodingRef, ?int $cmapRef): int
    {
        $definition = $font->definition;
        $registry = $this->writer->registry();

        $fontObject = $registry->allocate();
        $widthsObject = $registry->allocate();
        $descriptorObject = $registry->allocate();

        $this->writer->beginObject($fontObject);
        $this->writer->line('<</Type /Font');
        $this->writer->line('/BaseFont /' . $name);
        $this->writer->line('/Subtype /' . $definition->type);
        $this->writer->line('/FirstChar 32 /LastChar 255');
        $this->writer->line('/Widths ' . $widthsObject . ' 0 R');
        $this->writer->line('/FontDescriptor ' . $descriptorObject . ' 0 R');
        if ($encodingRef !== null) {
            $this->writer->line('/Encoding ' . $encodingRef . ' 0 R');
        } else {
            $this->writer->line('/Encoding /WinAnsiEncoding');
        }
        if ($cmapRef !== null) {
            $this->writer->line('/ToUnicode ' . $cmapRef . ' 0 R');
        }
        $this->writer->line('>>');
        $this->writer->endObject();

        $this->writer->beginObject($widthsObject);
        $widths = '[';
        for ($i = 32; $i <= 255; $i++) {
            $widths .= ($definition->charWidths[$i] ?? 0) . ' ';
        }
        $this->writer->line($widths . ']');
        $this->writer->endObject();

        $this->writer->beginObject($descriptorObject);
        $descriptor = '<</Type /FontDescriptor /FontName /' . $name;
        foreach ($definition->descriptor as $key => $value) {
            $descriptor .= ' /' . $key . ' ' . $value;
        }
        $path = $definition->fontFilePath();
        if ($path !== null && isset($this->fontFiles[$path])) {
            $key = $definition->type === 'Type1' ? 'FontFile' : 'FontFile2';
            $descriptor .= ' /' . $key . ' ' . $this->fontFiles[$path] . ' 0 R';
        }
        $this->writer->line($descriptor . '>>');
        $this->writer->endObject();

        return $fontObject;
    }
}
