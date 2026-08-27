<?php

declare(strict_types=1);

namespace Pdf\Import;

use Pdf\Exception\PdfException;

/**
 * A recursive-descent parser for PDF objects.
 *
 * Handles the object grammar needed to import a page: dictionaries, arrays,
 * names (with `#XX` escapes), literal and hex strings, numbers, references
 * (`N G R`), booleans, null, and indirect object bodies (`N G obj … endobj`,
 * including `stream … endstream`).
 *
 * Targets well-formed output from known tools — it fails loud on anything odd.
 */
final class PdfParser
{
    private const WHITESPACE = "\x00\x09\x0A\x0C\x0D\x20";
    private const DELIMITERS = "()<>[]{}/%";

    public function __construct(
        private readonly string $data,
        private int $pos = 0,
    ) {
    }

    public function position(): int
    {
        return $this->pos;
    }

    public function seek(int $pos): void
    {
        $this->pos = $pos;
    }

    /** Parse a single object value at the current position. */
    public function parseValue(): mixed
    {
        $this->skipWhitespace();
        if ($this->pos >= strlen($this->data)) {
            throw new PdfException('Unexpected end of PDF data.');
        }

        $ch = $this->data[$this->pos];

        return match (true) {
            $ch === '<' && ($this->data[$this->pos + 1] ?? '') === '<' => $this->parseDictionary(),
            $ch === '<' => $this->parseHexString(),
            $ch === '(' => $this->parseLiteralString(),
            $ch === '[' => $this->parseArray(),
            $ch === '/' => $this->parseName(),
            $ch === '-' || $ch === '+' || $ch === '.' || ctype_digit($ch) => $this->parseNumberOrReference(),
            default => $this->parseKeyword(),
        };
    }

    /**
     * Parse `N G obj <value> [stream…endstream] endobj` starting at the current
     * position. Returns the value, or a {@see PdfStream} when a stream follows.
     *
     * @param (\Closure(PdfReference): mixed)|null $resolveReference resolves an
     *        indirect `/Length`
     */
    public function parseIndirectObject(?\Closure $resolveReference = null): mixed
    {
        $this->skipWhitespace();
        $this->readInteger(); // object number
        $this->skipWhitespace();
        $this->readInteger(); // generation
        $this->skipWhitespace();
        $this->expectKeyword('obj');

        $value = $this->parseValue();
        $this->skipWhitespace();

        if ($this->peekKeyword('stream')) {
            $this->expectKeyword('stream');
            // The stream data starts after CRLF or LF.
            if (substr($this->data, $this->pos, 2) === "\r\n") {
                $this->pos += 2;
            } elseif (($this->data[$this->pos] ?? '') === "\n") {
                $this->pos += 1;
            } elseif (($this->data[$this->pos] ?? '') === "\r") {
                $this->pos += 1;
            }

            $dict = $value instanceof PdfDictionary ? $value->entries : [];

            $lengthValue = $dict['Length'] ?? null;
            if ($lengthValue instanceof PdfReference && $resolveReference !== null) {
                $lengthValue = $resolveReference($lengthValue);
            }
            $length = is_int($lengthValue) && $lengthValue >= 0 ? $lengthValue : null;

            $streamStart = $this->pos;
            if ($length !== null && $this->looksLikeEndstream($streamStart + $length)) {
                $raw = substr($this->data, $streamStart, $length);
                $this->pos = $streamStart + $length;
            } else {
                $raw = $this->scanToEndstream($streamStart);
            }

            $this->skipWhitespace();
            $this->expectKeyword('endstream');

            return new PdfStream($dict, $raw);
        }

        return $value;
    }

    public function expectKeyword(string $keyword): void
    {
        $this->skipWhitespace();
        if (substr($this->data, $this->pos, strlen($keyword)) !== $keyword) {
            throw new PdfException(sprintf(
                "Expected '%s' at offset %d, found '%s'.",
                $keyword,
                $this->pos,
                substr($this->data, $this->pos, 12),
            ));
        }
        $this->pos += strlen($keyword);
    }

    public function skipWhitespace(): void
    {
        while ($this->pos < strlen($this->data)) {
            $ch = $this->data[$this->pos];
            if (str_contains(self::WHITESPACE, $ch)) {
                $this->pos++;
            } elseif ($ch === '%') {
                $eol = strcspn($this->data, "\r\n", $this->pos);
                $this->pos += $eol;
            } else {
                break;
            }
        }
    }

    private function peekKeyword(string $keyword): bool
    {
        $save = $this->pos;
        $this->skipWhitespace();
        $found = substr($this->data, $this->pos, strlen($keyword)) === $keyword;
        $this->pos = $save;

        return $found;
    }

    private function parseDictionary(): PdfDictionary
    {
        $this->pos += 2; // <<
        $entries = [];
        while (true) {
            $this->skipWhitespace();
            if (substr($this->data, $this->pos, 2) === '>>') {
                $this->pos += 2;
                break;
            }
            if ($this->pos >= strlen($this->data)) {
                throw new PdfException('Unterminated dictionary in imported PDF.');
            }
            $key = $this->parseName();
            $entries[$key->value] = $this->parseValue();
        }

        return new PdfDictionary($entries);
    }

    /** @return list<mixed> */
    private function parseArray(): array
    {
        $this->pos++; // [
        $items = [];
        while (true) {
            $this->skipWhitespace();
            if (($this->data[$this->pos] ?? '') === ']') {
                $this->pos++;
                break;
            }
            if ($this->pos >= strlen($this->data)) {
                throw new PdfException('Unterminated array in imported PDF.');
            }
            $items[] = $this->parseValue();
        }

        return $items;
    }

    private function parseName(): PdfName
    {
        $this->pos++; // /
        $name = '';
        while ($this->pos < strlen($this->data)) {
            $ch = $this->data[$this->pos];
            if (str_contains(self::WHITESPACE, $ch) || str_contains(self::DELIMITERS, $ch)) {
                break;
            }
            if ($ch === '#' && ctype_xdigit($this->data[$this->pos + 1] ?? '') && ctype_xdigit($this->data[$this->pos + 2] ?? '')) {
                $name .= chr((int) hexdec(substr($this->data, $this->pos + 1, 2)));
                $this->pos += 3;
                continue;
            }
            $name .= $ch;
            $this->pos++;
        }

        return new PdfName($name);
    }

    private function parseLiteralString(): string
    {
        $this->pos++; // (
        $depth = 1;
        $out = '';
        while ($this->pos < strlen($this->data)) {
            $ch = $this->data[$this->pos++];
            if ($ch === '\\') {
                if ($this->pos >= strlen($this->data)) {
                    break;
                }
                $next = $this->data[$this->pos++];
                if ($next === "\r") {
                    if (($this->data[$this->pos] ?? '') === "\n") {
                        $this->pos++;
                    }
                    continue; // line continuation
                }
                if ($next === "\n") {
                    continue; // line continuation
                }
                $out .= match ($next) {
                    'n' => "\n", 'r' => "\r", 't' => "\t", 'b' => "\x08", 'f' => "\x0C",
                    '(' => '(', ')' => ')', '\\' => '\\',
                    default => ($next >= '0' && $next <= '7') ? $this->readOctal($next) : $next,
                };
                continue;
            }
            if ($ch === '(') {
                $depth++;
            } elseif ($ch === ')') {
                $depth--;
                if ($depth === 0) {
                    break;
                }
            }
            $out .= $ch;
        }

        return $out;
    }

    private function readOctal(string $first): string
    {
        $oct = $first;
        for ($i = 0; $i < 2; $i++) {
            $c = $this->data[$this->pos] ?? '';
            if ($c >= '0' && $c <= '7') {
                $oct .= $c;
                $this->pos++;
            } else {
                break;
            }
        }

        return chr(octdec($oct) & 0xFF);
    }

    private function parseHexString(): string
    {
        $this->pos++; // <
        $hex = '';
        while ($this->pos < strlen($this->data)) {
            $ch = $this->data[$this->pos++];
            if ($ch === '>') {
                break;
            }
            if (ctype_xdigit($ch)) {
                $hex .= $ch;
            }
        }
        if (strlen($hex) % 2 === 1) {
            $hex .= '0';
        }

        return (string) hex2bin($hex);
    }

    private function parseNumberOrReference(): int|float|PdfReference
    {
        $number = $this->readNumber();
        if (!is_int($number) || $number < 0) {
            return $number;
        }

        $afterFirst = $this->pos;
        $this->skipWhitespace();

        if (ctype_digit($this->data[$this->pos] ?? '')) {
            $gen = $this->readNumber();
            $this->skipWhitespace();
            $r = $this->data[$this->pos] ?? '';
            $afterR = $this->data[$this->pos + 1] ?? ' ';
            if (
                is_int($gen)
                && $r === 'R'
                && (str_contains(self::WHITESPACE, $afterR) || str_contains(self::DELIMITERS, $afterR))
            ) {
                $this->pos++; // R

                return new PdfReference($number, $gen);
            }
        }

        $this->pos = $afterFirst;

        return $number;
    }

    private function readNumber(): int|float
    {
        $this->skipWhitespace();
        $start = $this->pos;
        if (($this->data[$this->pos] ?? '') === '+' || ($this->data[$this->pos] ?? '') === '-') {
            $this->pos++;
        }
        $isFloat = false;
        while ($this->pos < strlen($this->data)) {
            $ch = $this->data[$this->pos];
            if (ctype_digit($ch)) {
                $this->pos++;
            } elseif ($ch === '.') {
                $isFloat = true;
                $this->pos++;
            } else {
                break;
            }
        }
        $text = substr($this->data, $start, $this->pos - $start);
        if ($text === '' || $text === '+' || $text === '-') {
            throw new PdfException('Invalid number at offset ' . $start . ' in imported PDF.');
        }

        return $isFloat ? (float) $text : (int) $text;
    }

    private function readInteger(): int
    {
        $n = $this->readNumber();
        if (!is_int($n)) {
            throw new PdfException('Expected an integer in imported PDF.');
        }

        return $n;
    }

    private function parseKeyword(): bool|null
    {
        $start = $this->pos;
        while ($this->pos < strlen($this->data)) {
            $ch = $this->data[$this->pos];
            if (str_contains(self::WHITESPACE, $ch) || str_contains(self::DELIMITERS, $ch)) {
                break;
            }
            $this->pos++;
        }
        $word = substr($this->data, $start, $this->pos - $start);

        return match ($word) {
            'true' => true,
            'false' => false,
            'null' => null,
            default => throw new PdfException("Unexpected token '{$word}' at offset {$start} in imported PDF."),
        };
    }

    /** Does `endstream` sit at $offset (allowing one optional EOL before it)? */
    private function looksLikeEndstream(int $offset): bool
    {
        $window = substr($this->data, $offset, 12);
        $window = ltrim($window, "\r\n \t");

        return str_starts_with($window, 'endstream');
    }

    /** Scan for `endstream` on an EOL boundary; the last resort. */
    private function scanToEndstream(int $start): string
    {
        $search = $start;
        while (true) {
            $end = strpos($this->data, 'endstream', $search);
            if ($end === false) {
                throw new PdfException('Unterminated stream in imported PDF.');
            }
            $before = $this->data[$end - 1] ?? '';
            if ($before === "\n" || $before === "\r" || $before === ' ' || $before === "\t") {
                $this->pos = $end;

                return rtrim(substr($this->data, $start, $end - $start), "\r\n");
            }
            $search = $end + 9;
        }
    }
}
