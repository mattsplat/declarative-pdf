<?php

declare(strict_types=1);

namespace Pdf\Image;

use Pdf\Exception\ImageException;

/**
 * Ported from `_parsepng()` / `_parsepngstream()` (fpdf.php:1302-1437):
 * IHDR parsing, colour-type mapping, rejection of 16-bit and interlaced PNGs,
 * a chunk scan for PLTE / tRNS / IDAT, and — for colour types 4 and 6 — the
 * alpha channel split into a separate soft mask (bumping the PDF version to
 * 1.4).
 */
final class PngDecoder
{
    private const SIGNATURE = "\x89PNG\r\n\x1a\n";

    public function decode(string $bytes, string $cacheKey): ImageResource
    {
        $reader = new ByteReader($bytes);

        if ($reader->bytes(8) !== self::SIGNATURE) {
            throw new ImageException('Not a PNG file: ' . $cacheKey);
        }

        $reader->skip(4); // IHDR length
        if ($reader->bytes(4) !== 'IHDR') {
            throw new ImageException('Incorrect PNG file: ' . $cacheKey);
        }

        $width = $reader->uint32();
        $height = $reader->uint32();
        $bitDepth = $reader->uint8();
        if ($bitDepth > 8) {
            throw new ImageException('16-bit depth not supported: ' . $cacheKey);
        }

        $colorType = $reader->uint8();
        $colorSpace = match ($colorType) {
            0, 4 => 'DeviceGray',
            2, 6 => 'DeviceRGB',
            3 => 'Indexed',
            default => throw new ImageException('Unknown colour type: ' . $cacheKey),
        };

        if ($reader->uint8() !== 0) {
            throw new ImageException('Unknown compression method: ' . $cacheKey);
        }
        if ($reader->uint8() !== 0) {
            throw new ImageException('Unknown filter method: ' . $cacheKey);
        }
        if ($reader->uint8() !== 0) {
            throw new ImageException('Interlacing not supported: ' . $cacheKey);
        }
        $reader->skip(4); // IHDR CRC

        $colors = $colorSpace === 'DeviceRGB' ? 3 : 1;
        $decodeParms = sprintf(
            '/Predictor 15 /Colors %d /BitsPerComponent %d /Columns %d',
            $colors,
            $bitDepth,
            $width,
        );

        $palette = '';
        /** @var list<int> $colorKeyMask */
        $colorKeyMask = [];
        $data = '';

        while (true) {
            $length = $reader->uint32();
            $type = $reader->bytes(4);
            if ($type === 'PLTE') {
                $palette = $reader->bytes($length);
                $reader->skip(4);
            } elseif ($type === 'tRNS') {
                $t = $reader->bytes($length);
                if ($colorType === 0) {
                    $colorKeyMask = [ord(substr($t, 1, 1))];
                } elseif ($colorType === 2) {
                    $colorKeyMask = [
                        ord(substr($t, 1, 1)),
                        ord(substr($t, 3, 1)),
                        ord(substr($t, 5, 1)),
                    ];
                } else {
                    $pos = strpos($t, "\x00");
                    if ($pos !== false) {
                        $colorKeyMask = [$pos];
                    }
                }
                $reader->skip(4);
            } elseif ($type === 'IDAT') {
                $data .= $reader->bytes($length);
                $reader->skip(4);
            } elseif ($type === 'IEND') {
                break;
            } else {
                $reader->skip($length + 4);
            }
        }

        if ($colorSpace === 'Indexed' && $palette === '') {
            throw new ImageException('Missing palette in ' . $cacheKey);
        }

        $softMask = null;
        $requiresPdf14 = false;

        if ($colorType >= 4) {
            [$data, $softMask] = $this->extractAlpha($data, $width, $height, $colorType, $cacheKey);
            $requiresPdf14 = true;
        }

        return new ImageResource(
            widthPx: $width,
            heightPx: $height,
            colorSpace: $colorSpace,
            bitsPerComponent: $bitDepth,
            filter: 'FlateDecode',
            data: $data,
            cacheKey: $cacheKey,
            decodeParms: $decodeParms,
            palette: $palette !== '' ? $palette : null,
            colorKeyMask: $colorKeyMask,
            softMask: $softMask,
            requiresPdf14: $requiresPdf14,
        );
    }

    /**
     * Split interleaved colour+alpha scanlines into two Flate streams.
     * Ported verbatim from fpdf.php:1397-1430.
     *
     * @return array{0: string, 1: string} [colourData, alphaData]
     */
    private function extractAlpha(string $data, int $width, int $height, int $colorType, string $cacheKey): array
    {
        $inflated = @gzuncompress($data);
        if ($inflated === false) {
            throw new ImageException('Cannot inflate PNG image data: ' . $cacheKey);
        }

        $color = '';
        $alpha = '';

        if ($colorType === 4) {
            $lineLength = 2 * $width;
            $keepColor = '/(.)./s';
            $keepAlpha = '/.(.)/s';
        } else {
            $lineLength = 4 * $width;
            $keepColor = '/(.{3})./s';
            $keepAlpha = '/.{3}(.)/s';
        }

        for ($row = 0; $row < $height; $row++) {
            $pos = (1 + $lineLength) * $row;
            $filterByte = $inflated[$pos];
            $color .= $filterByte;
            $alpha .= $filterByte;
            $line = substr($inflated, $pos + 1, $lineLength);
            $color .= (string) preg_replace($keepColor, '$1', $line);
            $alpha .= (string) preg_replace($keepAlpha, '$1', $line);
        }

        $colorCompressed = gzcompress($color);
        $alphaCompressed = gzcompress($alpha);
        if ($colorCompressed === false || $alphaCompressed === false) {
            throw new ImageException('Cannot recompress PNG channels: ' . $cacheKey);
        }

        return [$colorCompressed, $alphaCompressed];
    }
}
