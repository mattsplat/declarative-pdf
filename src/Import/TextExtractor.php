<?php

declare(strict_types=1);

namespace Pdf\Import;

use Pdf\Exception\PdfException;

/**
 * Best-effort plain-text extraction from an imported PDF.
 *
 * Tokenizes each page's content stream and interprets the text-positioning
 * and text-showing operators (`BT`/`ET`, `Tf`, `Td`/`TD`/`Tm`/`T*`,
 * `Tc`/`Tw`/`Tz`/`TL`, `Tj`/`TJ`/`'`/`"`) plus `q`/`Q`/`cm`; the actual state
 * machine lives in {@see TextExtractorState}. Each shown string is decoded
 * through its font's `/ToUnicode` CMap where present, or Windows-1252
 * otherwise.
 *
 * Deliberately out of scope: `/Type0` composite fonts decode only when they
 * carry a `/ToUnicode` CMap (no CID width table, so spacing around them is
 * approximate); a simple font's `/Differences` re-mapping is ignored — it
 * falls back to WinAnsi like any other font without `/ToUnicode`; content
 * painted via a `Do` (Form XObject) is not visited; reading order is simply
 * top-to-bottom as drawn, not column-aware. Malformed content stops
 * extraction for that page rather than throwing — this is a best-effort
 * reader, not the structural importer.
 */
final class TextExtractor
{
    public function __construct(private readonly PdfImportDocument $document)
    {
    }

    public static function fromFile(string $path): self
    {
        return new self(PdfImportDocument::fromFile($path));
    }

    /** @return list<string> extracted text, one entry per page in page order */
    public function pages(): array
    {
        $pages = [];
        for ($n = 1; $n <= $this->document->pageCount(); $n++) {
            $pages[] = self::extractPage($this->document->page($n));
        }

        return $pages;
    }

    /** The whole document's text, pages joined by a blank line. */
    public function text(): string
    {
        return implode("\n\n", $this->pages());
    }

    public static function extractPage(ImportedPage $page): string
    {
        $state = new TextExtractorState($page);
        $data = $page->contentBytes;
        $length = strlen($data);
        $parser = new PdfParser($data);
        /** @var list<mixed> $operands */
        $operands = [];

        try {
            while (true) {
                $parser->skipWhitespace();
                if ($parser->position() >= $length) {
                    break;
                }

                $ch = $data[$parser->position()];
                if ($ch === '-' || $ch === '+' || $ch === '.' || ctype_digit($ch)
                    || $ch === '(' || $ch === '<' || $ch === '[' || $ch === '/'
                ) {
                    $operands[] = $parser->parseValue();
                    continue;
                }

                $operator = $parser->readBareWord();
                if ($operator === '') {
                    break; // an unrecognised delimiter byte at the top level; give up on this page
                }
                if ($operator === 'BI') {
                    self::skipInlineImage($data, $parser);
                    $operands = [];
                    continue;
                }

                self::applyOperator($operator, $operands, $state);
                $operands = [];
            }
        } catch (PdfException) {
            // Best-effort: keep whatever text was recovered before the parse broke down.
        }

        return $state->text();
    }

    /** @param list<mixed> $operands */
    private static function applyOperator(string $operator, array $operands, TextExtractorState $state): void
    {
        match ($operator) {
            'q' => $state->pushCtm(),
            'Q' => $state->popCtm(),
            'cm' => $state->concatCtm(self::floats($operands)),
            'BT' => $state->beginText(),
            'Tf' => $state->setFont($operands[0] ?? null, self::float($operands[1] ?? null)),
            'Tc' => $state->setCharSpacing(self::float($operands[0] ?? null)),
            'Tw' => $state->setWordSpacing(self::float($operands[0] ?? null)),
            'Tz' => $state->setHorizontalScaling(self::float($operands[0] ?? null, 100.0)),
            'TL' => $state->setLeading(self::float($operands[0] ?? null)),
            'Td' => $state->translateLine(self::float($operands[0] ?? null), self::float($operands[1] ?? null)),
            'TD' => $state->translateLineAndSetLeading(self::float($operands[0] ?? null), self::float($operands[1] ?? null)),
            'Tm' => $state->setTextMatrix(self::floats($operands)),
            'T*' => $state->newline(),
            'Tj' => $state->showText(self::string($operands[0] ?? null)),
            "'" => $state->nextLineAndShowText(self::string($operands[0] ?? null)),
            '"' => $state->showTextWithSpacing(self::float($operands[0] ?? null), self::float($operands[1] ?? null), self::string($operands[2] ?? null)),
            'TJ' => self::showTextArray($operands[0] ?? null, $state),
            default => null,
        };
    }

    private static function showTextArray(mixed $array, TextExtractorState $state): void
    {
        if (!is_array($array)) {
            return;
        }
        foreach ($array as $item) {
            if (is_string($item)) {
                $state->showText($item);
            } elseif (is_int($item) || is_float($item)) {
                $state->adjustByTJNumber((float) $item);
            }
        }
    }

    /**
     * @param list<mixed> $operands
     * @return list<float>
     */
    private static function floats(array $operands): array
    {
        return array_map(static fn (mixed $v): float => self::float($v), $operands);
    }

    private static function float(mixed $value, float $default = 0.0): float
    {
        return is_int($value) || is_float($value) ? (float) $value : $default;
    }

    private static function string(mixed $value): string
    {
        return is_string($value) ? $value : '';
    }

    /** Skip a `BI … ID <binary data> EI` inline image: its bytes could otherwise be mistaken for operators. */
    private static function skipInlineImage(string $data, PdfParser $parser): void
    {
        $idPos = strpos($data, 'ID', $parser->position());
        if ($idPos === false) {
            $parser->seek(strlen($data));

            return;
        }
        $dataStart = $idPos + 2;
        if (($data[$dataStart] ?? '') !== '' && str_contains("\x00\x09\x0A\x0C\x0D\x20", $data[$dataStart])) {
            $dataStart++;
        }

        $search = $dataStart;
        while (true) {
            $eiPos = strpos($data, 'EI', $search);
            if ($eiPos === false) {
                $parser->seek(strlen($data));

                return;
            }
            $before = $eiPos > 0 ? $data[$eiPos - 1] : "\x0A";
            $after = $data[$eiPos + 2] ?? "\x0A";
            if (str_contains("\x00\x09\x0A\x0C\x0D\x20", $before) && str_contains("\x00\x09\x0A\x0C\x0D\x20", $after)) {
                $parser->seek($eiPos + 2);

                return;
            }
            $search = $eiPos + 2;
        }
    }
}
