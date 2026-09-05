<?php

declare(strict_types=1);

namespace Pdf\Import;

/**
 * Mutable text/graphics state for one page's worth of {@see TextExtractor}
 * interpretation: the CTM stack, the text and line matrices, the text-state
 * scalars (`Tc`/`Tw`/`Tz`/`TL`), and the accumulated output.
 *
 * @internal used only by {@see TextExtractor}; not part of the public API.
 */
final class TextExtractorState
{
    private const DEFAULT_GLYPH_WIDTH = 500.0;

    /** A visual gap smaller than this fraction of the font size is kerning, not a word space. */
    private const SPACE_GAP_RATIO = 0.12;

    /** A vertical move larger than this fraction of the font size starts a new line. */
    private const LINE_GAP_RATIO = 0.35;

    private const IDENTITY = [1.0, 0.0, 0.0, 1.0, 0.0, 0.0];

    /** @var list<array{0:float,1:float,2:float,3:float,4:float,5:float}> */
    private array $ctmStack = [];

    /** @var array{0:float,1:float,2:float,3:float,4:float,5:float} */
    private array $ctm = self::IDENTITY;

    /** @var array{0:float,1:float,2:float,3:float,4:float,5:float} */
    private array $tm = self::IDENTITY;

    /** @var array{0:float,1:float,2:float,3:float,4:float,5:float} */
    private array $tlm = self::IDENTITY;

    private float $tc = 0.0;
    private float $tw = 0.0;
    private float $tz = 100.0;
    private float $tl = 0.0;

    private ?string $fontName = null;
    private float $fontSizePt = 0.0;

    /** @var array<string, array{bytesPerCode: int, unicode: array<int, string>, widths: array<int, float>, missingWidth: float}> */
    private array $fontCache = [];

    private string $buffer = '';
    private ?float $lastX = null;
    private ?float $lastY = null;

    public function __construct(private readonly ImportedPage $page)
    {
    }

    public function text(): string
    {
        return $this->buffer;
    }

    public function pushCtm(): void
    {
        $this->ctmStack[] = $this->ctm;
    }

    public function popCtm(): void
    {
        $this->ctm = array_pop($this->ctmStack) ?? $this->ctm;
    }

    /** @param list<float> $operands */
    public function concatCtm(array $operands): void
    {
        $m = self::matrix($operands);
        if ($m !== null) {
            $this->ctm = self::matMul($m, $this->ctm);
        }
    }

    public function beginText(): void
    {
        $this->tm = $this->tlm = self::IDENTITY;
    }

    public function setFont(mixed $name, float $sizePt): void
    {
        $this->fontSizePt = $sizePt;
        $this->fontName = $name instanceof PdfName ? $name->value : null;
        if ($this->fontName !== null && !isset($this->fontCache[$this->fontName])) {
            $this->fontCache[$this->fontName] = self::resolveFont($this->fontName, $this->page);
        }
    }

    public function setCharSpacing(float $tc): void
    {
        $this->tc = $tc;
    }

    public function setWordSpacing(float $tw): void
    {
        $this->tw = $tw;
    }

    public function setHorizontalScaling(float $tz): void
    {
        $this->tz = $tz;
    }

    public function setLeading(float $tl): void
    {
        $this->tl = $tl;
    }

    public function translateLine(float $tx, float $ty): void
    {
        $this->tlm = self::matMul([1.0, 0.0, 0.0, 1.0, $tx, $ty], $this->tlm);
        $this->tm = $this->tlm;
    }

    public function translateLineAndSetLeading(float $tx, float $ty): void
    {
        $this->tl = -$ty;
        $this->translateLine($tx, $ty);
    }

    /** @param list<float> $operands */
    public function setTextMatrix(array $operands): void
    {
        $m = self::matrix($operands);
        if ($m !== null) {
            $this->tlm = $this->tm = $m;
        }
    }

    public function newline(): void
    {
        $this->translateLine(0.0, -$this->tl);
    }

    public function nextLineAndShowText(string $raw): void
    {
        $this->newline();
        $this->showText($raw);
    }

    public function showTextWithSpacing(float $wordSpacing, float $charSpacing, string $raw): void
    {
        $this->tw = $wordSpacing;
        $this->tc = $charSpacing;
        $this->nextLineAndShowText($raw);
    }

    /** A `TJ` array's numeric element: a pure kerning move, no text shown. */
    public function adjustByTJNumber(float $thousandthsOfEm): void
    {
        $tx = -$thousandthsOfEm / 1000.0 * $this->fontSizePt * ($this->tz / 100.0);
        $this->tm = self::matMul([1.0, 0.0, 0.0, 1.0, $tx, 0.0], $this->tm);
    }

    public function showText(string $raw): void
    {
        if ($raw === '') {
            return;
        }
        $font = $this->fontCache[$this->fontName ?? ''] ?? null;
        if ($font === null) {
            return;
        }

        [$originX, $originY] = self::applyMatrix(self::matMul($this->tm, $this->ctm), 0.0, 0.0);
        $this->placeGap($originX, $originY);

        $codes = self::codes($raw, $font['bytesPerCode']);
        $this->buffer .= self::decode($codes, $font);

        $advance = 0.0;
        foreach ($codes as $code) {
            $width = $font['widths'][$code] ?? $font['missingWidth'];
            $wordSpacing = ($font['bytesPerCode'] === 1 && $code === 0x20) ? $this->tw : 0.0;
            $advance += (($width / 1000.0) * $this->fontSizePt + $this->tc + $wordSpacing) * ($this->tz / 100.0);
        }

        $this->tm = self::matMul([1.0, 0.0, 0.0, 1.0, $advance, 0.0], $this->tm);
        [$this->lastX, $this->lastY] = self::applyMatrix(self::matMul($this->tm, $this->ctm), 0.0, 0.0);
    }

    private function placeGap(float $x, float $y): void
    {
        if ($this->lastY === null) {
            return;
        }

        if (abs($y - $this->lastY) > max(1.0, $this->fontSizePt * self::LINE_GAP_RATIO)) {
            $this->buffer .= "\n";

            return;
        }

        $gap = $x - ($this->lastX ?? $x);
        if ($gap > max(0.3, $this->fontSizePt * self::SPACE_GAP_RATIO) && !str_ends_with($this->buffer, ' ') && !str_ends_with($this->buffer, "\n")) {
            $this->buffer .= ' ';
        }
    }

    /** @return list<int> */
    private static function codes(string $raw, int $bytesPerCode): array
    {
        $codes = [];
        $length = strlen($raw);
        for ($i = 0; $i + $bytesPerCode <= $length; $i += $bytesPerCode) {
            $codes[] = $bytesPerCode === 2
                ? (ord($raw[$i]) << 8) | ord($raw[$i + 1])
                : ord($raw[$i]);
        }

        return $codes;
    }

    /**
     * @param list<int>                                                                                                      $codes
     * @param array{bytesPerCode: int, unicode: array<int, string>, widths: array<int, float>, missingWidth: float} $font
     */
    private static function decode(array $codes, array $font): string
    {
        if ($font['unicode'] !== []) {
            $out = '';
            foreach ($codes as $code) {
                $out .= $font['unicode'][$code] ?? ($font['bytesPerCode'] === 1 ? self::winAnsiChar($code) : '');
            }

            return $out;
        }

        if ($font['bytesPerCode'] === 2) {
            return ''; // a composite font with neither /ToUnicode nor a supported width table — nothing safe to decode
        }

        $bytes = implode('', array_map(static fn (int $code): string => chr($code & 0xFF), $codes));

        return self::winAnsiBytes($bytes);
    }

    private static function winAnsiChar(int $code): string
    {
        return self::winAnsiBytes(chr($code & 0xFF));
    }

    private static function winAnsiBytes(string $bytes): string
    {
        return mb_convert_encoding($bytes, 'UTF-8', 'Windows-1252');
    }

    /** @return array{bytesPerCode: int, unicode: array<int, string>, widths: array<int, float>, missingWidth: float} */
    private static function resolveFont(string $name, ImportedPage $page): array
    {
        $default = ['bytesPerCode' => 1, 'unicode' => [], 'widths' => [], 'missingWidth' => self::DEFAULT_GLYPH_WIDTH];

        $fonts = self::resolve($page->resources->get('Font'), $page->dependencies);
        if (!$fonts instanceof PdfDictionary) {
            return $default;
        }
        $font = self::resolve($fonts->get($name), $page->dependencies);
        if (!$font instanceof PdfDictionary) {
            return $default;
        }

        $subtype = self::resolve($font->get('Subtype'), $page->dependencies);
        $isType0 = $subtype instanceof PdfName && $subtype->value === 'Type0';

        $unicode = [];
        $toUnicode = self::resolve($font->get('ToUnicode'), $page->dependencies);
        if ($toUnicode instanceof PdfStream) {
            try {
                $unicode = ToUnicodeCmapParser::parse($toUnicode->decoded());
            } catch (\Pdf\Exception\PdfException) {
                // A malformed embedded CMap shouldn't take down extraction of the rest of the page.
            }
        }

        [$widths, $missingWidth] = $isType0
            ? [[], self::DEFAULT_GLYPH_WIDTH]
            : self::simpleFontWidths($font, $page->dependencies);

        return [
            'bytesPerCode' => $isType0 ? 2 : 1,
            'unicode' => $unicode,
            'widths' => $widths,
            'missingWidth' => $missingWidth,
        ];
    }

    /**
     * @param array<int, mixed> $dependencies
     * @return array{0: array<int, float>, 1: float}
     */
    private static function simpleFontWidths(PdfDictionary $font, array $dependencies): array
    {
        $widths = [];
        $firstChar = self::resolve($font->get('FirstChar'), $dependencies);
        $widthsArray = self::resolve($font->get('Widths'), $dependencies);
        if (is_int($firstChar) && is_array($widthsArray)) {
            foreach ($widthsArray as $offset => $width) {
                $width = self::resolve($width, $dependencies);
                if (is_int($width) || is_float($width)) {
                    $widths[$firstChar + (int) $offset] = (float) $width;
                }
            }
        }

        $missingWidth = self::DEFAULT_GLYPH_WIDTH;
        $descriptor = self::resolve($font->get('FontDescriptor'), $dependencies);
        if ($descriptor instanceof PdfDictionary) {
            $declared = self::resolve($descriptor->get('MissingWidth'), $dependencies);
            if (is_int($declared) || is_float($declared)) {
                $missingWidth = (float) $declared;
            }
        }

        return [$widths, $missingWidth];
    }

    /** @param array<int, mixed> $dependencies */
    private static function resolve(mixed $value, array $dependencies): mixed
    {
        return $value instanceof PdfReference ? ($dependencies[$value->number] ?? null) : $value;
    }

    /**
     * @param list<float> $operands
     * @return array{0:float,1:float,2:float,3:float,4:float,5:float}|null
     */
    private static function matrix(array $operands): ?array
    {
        if (count($operands) !== 6) {
            return null;
        }

        return [$operands[0], $operands[1], $operands[2], $operands[3], $operands[4], $operands[5]];
    }

    /**
     * @param array{0:float,1:float,2:float,3:float,4:float,5:float} $m1
     * @param array{0:float,1:float,2:float,3:float,4:float,5:float} $m2
     * @return array{0:float,1:float,2:float,3:float,4:float,5:float}
     */
    private static function matMul(array $m1, array $m2): array
    {
        return [
            $m1[0] * $m2[0] + $m1[1] * $m2[2],
            $m1[0] * $m2[1] + $m1[1] * $m2[3],
            $m1[2] * $m2[0] + $m1[3] * $m2[2],
            $m1[2] * $m2[1] + $m1[3] * $m2[3],
            $m1[4] * $m2[0] + $m1[5] * $m2[2] + $m2[4],
            $m1[4] * $m2[1] + $m1[5] * $m2[3] + $m2[5],
        ];
    }

    /**
     * @param array{0:float,1:float,2:float,3:float,4:float,5:float} $m
     * @return array{0: float, 1: float}
     */
    private static function applyMatrix(array $m, float $x, float $y): array
    {
        return [$m[0] * $x + $m[2] * $y + $m[4], $m[1] * $x + $m[3] * $y + $m[5]];
    }
}
