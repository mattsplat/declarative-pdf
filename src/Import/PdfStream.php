<?php

declare(strict_types=1);

namespace Pdf\Import;

use Pdf\Exception\PdfException;

/**
 * A stream object: its dictionary plus its raw (still-encoded) bytes.
 */
final class PdfStream
{
    private ?string $decoded = null;

    /** @param array<string, mixed> $dict */
    public function __construct(
        public readonly array $dict,
        public readonly string $rawData,
    ) {
    }

    /**
     * Decode the stream by applying its full filter chain
     * (FlateDecode / ASCIIHexDecode / ASCII85Decode) followed by any
     * `/DecodeParms` predictor. LZW and image filters are not supported and
     * fail loud — content, object and cross-reference streams are always one of
     * the above.
     */
    public function decoded(): string
    {
        if ($this->decoded !== null) {
            return $this->decoded;
        }

        $data = $this->rawData;
        $filters = $this->names($this->dict['Filter'] ?? $this->dict['F'] ?? null);
        $parmsList = $this->parmsList(count($filters));

        foreach ($filters as $i => $filter) {
            $data = match ($filter) {
                'FlateDecode', 'Fl' => $this->unpredict(self::inflate($data), $parmsList[$i]),
                'ASCIIHexDecode', 'AHx' => self::asciiHexDecode($data),
                'ASCII85Decode', 'A85' => self::ascii85Decode($data),
                default => throw new PdfException('Unsupported stream filter for import: ' . $filter),
            };
        }

        return $this->decoded = $data;
    }

    /**
     * @param mixed $value
     * @return list<string>
     */
    private function names(mixed $value): array
    {
        if ($value === null) {
            return [];
        }
        $value = is_array($value) ? $value : [$value];

        return array_values(array_map(
            static fn ($f) => $f instanceof PdfName ? $f->value : (string) $f,
            $value,
        ));
    }

    /**
     * @return list<array<string, mixed>|null> one entry per filter
     */
    private function parmsList(int $filterCount): array
    {
        $parms = $this->dict['DecodeParms'] ?? $this->dict['DP'] ?? null;
        if ($parms instanceof PdfDictionary) {
            $parms = [$parms->entries];
        } elseif (is_array($parms)) {
            $parms = array_map(
                static fn ($p) => $p instanceof PdfDictionary ? $p->entries : null,
                $parms,
            );
        } else {
            $parms = [];
        }

        /** @var list<array<string, mixed>|null> $out */
        $out = [];
        for ($i = 0; $i < max(1, $filterCount); $i++) {
            $out[$i] = $parms[$i] ?? null;
        }

        return $out;
    }

    /** @param array<string, mixed>|null $parms */
    private function unpredict(string $data, ?array $parms): string
    {
        $predictor = is_int($parms['Predictor'] ?? null) ? $parms['Predictor'] : 1;
        if ($predictor <= 1 || $parms === null) {
            return $data;
        }

        $colors = is_int($parms['Colors'] ?? null) ? $parms['Colors'] : 1;
        $bpc = is_int($parms['BitsPerComponent'] ?? null) ? $parms['BitsPerComponent'] : 8;
        $columns = is_int($parms['Columns'] ?? null) ? $parms['Columns'] : 1;

        $bytesPerPixel = max(1, intdiv($colors * $bpc, 8));
        $rowLength = intdiv($colors * $bpc * $columns + 7, 8);
        if ($rowLength <= 0) {
            return $data;
        }

        if ($predictor === 2) {
            return self::tiffPredictor($data, $rowLength, $bytesPerPixel);
        }

        return self::pngPredictor($data, $rowLength, $bytesPerPixel);
    }

    private static function tiffPredictor(string $data, int $rowLength, int $bpp): string
    {
        $out = '';
        for ($i = 0; $i + $rowLength <= strlen($data); $i += $rowLength) {
            $row = substr($data, $i, $rowLength);
            for ($j = $bpp; $j < $rowLength; $j++) {
                $row[$j] = chr((ord($row[$j]) + ord($row[$j - $bpp])) & 0xFF);
            }
            $out .= $row;
        }

        return $out;
    }

    private static function pngPredictor(string $data, int $rowLength, int $bpp): string
    {
        $stride = $rowLength + 1; // one filter-type byte per row
        $out = '';
        $previous = str_repeat("\x00", $rowLength);

        for ($i = 0; $i + $stride <= strlen($data); $i += $stride) {
            $filterType = ord($data[$i]);
            $row = substr($data, $i + 1, $rowLength);
            $decoded = '';

            for ($j = 0; $j < $rowLength; $j++) {
                $x = ord($row[$j]);
                $a = $j >= $bpp ? ord($decoded[$j - $bpp]) : 0;
                $b = ord($previous[$j]);
                $c = $j >= $bpp ? ord($previous[$j - $bpp]) : 0;

                $value = match ($filterType) {
                    1 => $x + $a,
                    2 => $x + $b,
                    3 => $x + intdiv($a + $b, 2),
                    4 => $x + self::paeth($a, $b, $c),
                    default => $x,
                };
                $decoded .= chr($value & 0xFF);
            }

            $out .= $decoded;
            $previous = $decoded;
        }

        return $out;
    }

    private static function paeth(int $a, int $b, int $c): int
    {
        $p = $a + $b - $c;
        $pa = abs($p - $a);
        $pb = abs($p - $b);
        $pc = abs($p - $c);

        return $pa <= $pb && $pa <= $pc ? $a : ($pb <= $pc ? $b : $c);
    }

    private static function inflate(string $data): string
    {
        $out = @gzuncompress($data);
        if ($out === false) {
            $out = @gzinflate($data);
        }
        if ($out === false) {
            $out = @gzinflate(substr($data, 2)); // skip a stray zlib header
        }
        if ($out === false) {
            throw new PdfException('Failed to inflate an imported stream.');
        }

        return $out;
    }

    private static function asciiHexDecode(string $data): string
    {
        $hex = preg_replace('/[^0-9A-Fa-f]/', '', strstr($data, '>', true) ?: $data) ?? '';
        if (strlen($hex) % 2 === 1) {
            $hex .= '0';
        }

        return (string) hex2bin($hex);
    }

    private static function ascii85Decode(string $data): string
    {
        $data = trim(str_replace(['<~', '~>'], '', $data));
        $data = (string) preg_replace('/\s+/', '', $data);
        $out = '';
        $tuple = 0;
        $count = 0;

        $length = strlen($data);
        for ($i = 0; $i < $length; $i++) {
            $ch = $data[$i];
            if ($ch === 'z' && $count === 0) {
                $out .= "\x00\x00\x00\x00";
                continue;
            }
            $tuple = $tuple * 85 + (ord($ch) - 33);
            if (++$count === 5) {
                $out .= pack('N', $tuple);
                $tuple = 0;
                $count = 0;
            }
        }
        if ($count > 0) {
            for ($k = $count; $k < 5; $k++) {
                $tuple = $tuple * 85 + 84;
            }
            $out .= substr(pack('N', $tuple), 0, $count - 1);
        }

        return $out;
    }
}
