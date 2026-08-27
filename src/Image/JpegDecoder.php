<?php

declare(strict_types=1);

namespace Pdf\Image;

use Pdf\Exception\ImageException;

/**
 * Ported from `_parsejpg()` (fpdf.php:1283): channel count -> colour space,
 * bit depth from the marker, raw bytes with a DCTDecode filter.
 */
final class JpegDecoder
{
    public function decode(string $bytes, string $cacheKey): ImageResource
    {
        $info = @getimagesizefromstring($bytes);
        if ($info === false) {
            throw new ImageException('Missing or incorrect JPEG data: ' . $cacheKey);
        }
        if ($info[2] !== IMAGETYPE_JPEG) {
            throw new ImageException('Not a JPEG file: ' . $cacheKey);
        }

        $channels = $info['channels'] ?? 3;
        $colorSpace = match ($channels) {
            4 => 'DeviceCMYK',
            1 => 'DeviceGray',
            default => 'DeviceRGB',
        };

        return new ImageResource(
            widthPx: $info[0],
            heightPx: $info[1],
            colorSpace: $colorSpace,
            bitsPerComponent: $info['bits'] ?? 8,
            filter: 'DCTDecode',
            data: $bytes,
            cacheKey: $cacheKey,
        );
    }
}
