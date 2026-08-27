<?php

declare(strict_types=1);

namespace Pdf\Font;

/**
 * A parsed font definition file.
 *
 * Fields mirror the JSON schema loaded by FPDF's `_loadjsonfont()`
 * (fpdf.php:1145): `type`, `name`, `up`, `ut`, `cw`, `enc`, `uv` for every
 * font, plus `desc`, `diff`, `file`, `originalSize`, `subsetted` for embedded
 * TrueType/Type1 fonts.
 */
final readonly class FontDefinition
{
    /**
     * @param array<int, int>      $charWidths   256 glyph advances, indexed by byte value (units/1000em)
     * @param array<string, mixed> $descriptor   FontDescriptor entries (embedded fonts only)
     * @param array<int|string, int|array{0:int,1:int}> $unicodeMap  byte => codepoint, or byte => [codepoint, count]
     */
    public function __construct(
        public string $type,
        public string $name,
        public int $underlinePosition,
        public int $underlineThickness,
        public array $charWidths,
        public ?string $encoding = null,
        public array $unicodeMap = [],
        public array $descriptor = [],
        public ?string $differences = null,
        public ?string $file = null,
        public ?int $originalSize = null,
        public ?int $size1 = null,
        public ?int $size2 = null,
        public bool $subsetted = false,
        public ?string $sourceDirectory = null,
    ) {
    }

    /** Absolute path to the embedded font program, if any. */
    public function fontFilePath(): ?string
    {
        if (!$this->isEmbedded()) {
            return null;
        }

        return $this->sourceDirectory !== null
            ? $this->sourceDirectory . '/' . $this->file
            : $this->file;
    }

    public function isCore(): bool
    {
        return $this->type === 'Core';
    }

    public function isEmbedded(): bool
    {
        return $this->file !== null && $this->file !== '';
    }

    public function metrics(): FontMetrics
    {
        return new FontMetrics($this->charWidths);
    }
}
