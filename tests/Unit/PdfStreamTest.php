<?php

declare(strict_types=1);

namespace Pdf\Tests\Unit;

use Pdf\Import\PdfDictionary;
use Pdf\Import\PdfName;
use Pdf\Import\PdfStream;
use PHPUnit\Framework\TestCase;

final class PdfStreamTest extends TestCase
{
    public function test_flate_without_predictor(): void
    {
        $payload = 'the quick brown fox';
        $stream = new PdfStream(
            ['Filter' => new PdfName('FlateDecode')],
            (string) gzcompress($payload),
        );

        self::assertSame($payload, $stream->decoded());
    }

    public function test_png_up_predictor_is_reversed_and_the_filter_byte_stripped(): void
    {
        // Two 4-byte rows; PNG "Up" (filter type 2) encodes row2 as row2 - row1.
        $row1 = "\x00\x00\x00\x0A";
        $row2 = "\x00\x00\x00\x14";
        $encoded = "\x02" . $row1
            . "\x02" . self::sub($row2, $row1);

        $stream = new PdfStream(
            [
                'Filter' => new PdfName('FlateDecode'),
                'DecodeParms' => new PdfDictionary(['Predictor' => 12, 'Columns' => 4]),
            ],
            (string) gzcompress($encoded),
        );

        self::assertSame($row1 . $row2, $stream->decoded());
    }

    public function test_png_none_predictor_still_strips_the_per_row_tag_byte(): void
    {
        $rows = "\x00" . 'ABCD' . "\x00" . 'EFGH';
        $stream = new PdfStream(
            [
                'Filter' => new PdfName('FlateDecode'),
                'DecodeParms' => new PdfDictionary(['Predictor' => 15, 'Columns' => 4]),
            ],
            (string) gzcompress($rows),
        );

        self::assertSame('ABCDEFGH', $stream->decoded());
    }

    public function test_tiff_predictor(): void
    {
        // Predictor 2, one row, 8-bit: each byte is a delta from the previous.
        $original = "\x0A\x02\x03\x01";
        $deltas = "\x0A\xF8\x01\xFE"; // 10, then -8, +1, -2
        $stream = new PdfStream(
            [
                'Filter' => new PdfName('FlateDecode'),
                'DecodeParms' => new PdfDictionary(['Predictor' => 2, 'Columns' => 4, 'Colors' => 1, 'BitsPerComponent' => 8]),
            ],
            (string) gzcompress($deltas),
        );

        self::assertSame($original, $stream->decoded());
    }

    public function test_ascii85_then_flate_filter_chain(): void
    {
        $payload = 'chained filters work';
        $flated = (string) gzcompress($payload);
        $ascii85 = self::ascii85Encode($flated);

        $stream = new PdfStream(
            ['Filter' => [new PdfName('ASCII85Decode'), new PdfName('FlateDecode')]],
            $ascii85,
        );

        self::assertSame($payload, $stream->decoded());
    }

    public function test_unsupported_filter_fails_loud(): void
    {
        $stream = new PdfStream(['Filter' => new PdfName('LZWDecode')], 'x');

        $this->expectExceptionMessage('Unsupported stream filter');
        $stream->decoded();
    }

    private static function sub(string $a, string $b): string
    {
        $out = '';
        for ($i = 0; $i < strlen($a); $i++) {
            $out .= chr((ord($a[$i]) - ord($b[$i])) & 0xFF);
        }

        return $out;
    }

    private static function ascii85Encode(string $data): string
    {
        $out = '';
        foreach (str_split($data, 4) as $chunk) {
            $pad = 4 - strlen($chunk);
            $chunk = str_pad($chunk, 4, "\x00");
            $n = unpack('N', $chunk)[1];
            $group = '';
            for ($i = 0; $i < 5; $i++) {
                $group = chr($n % 85 + 33) . $group;
                $n = intdiv($n, 85);
            }
            $out .= $pad > 0 ? substr($group, 0, 5 - $pad) : $group;
        }

        return '<~' . $out . '~>';
    }
}
