<?php

declare(strict_types=1);

namespace Pdf\Image;

/**
 * A decoded image ready to be written as a PDF image XObject.
 *
 * Fields mirror the `$info` array FPDF's `_parse*` methods return
 * (fpdf.php:1299, 1391): colour space, bits per component, stream filter,
 * compressed data, optional palette, colour-key mask and soft mask.
 */
final readonly class ImageResource
{
    /**
     * @param 'DeviceRGB'|'DeviceGray'|'DeviceCMYK'|'Indexed' $colorSpace
     * @param 'DCTDecode'|'FlateDecode'|null                  $filter
     * @param list<int>                                       $colorKeyMask  tRNS colour key, per component
     */
    public function __construct(
        public int $widthPx,
        public int $heightPx,
        public string $colorSpace,
        public int $bitsPerComponent,
        public ?string $filter,
        public string $data,
        public string $cacheKey,
        public ?string $decodeParms = null,
        public ?string $palette = null,
        public array $colorKeyMask = [],
        public ?string $softMask = null,
        public bool $requiresPdf14 = false,
    ) {
    }

    public function hasAlpha(): bool
    {
        return $this->softMask !== null;
    }

    public function aspectRatio(): float
    {
        return $this->heightPx === 0 ? 1.0 : $this->widthPx / $this->heightPx;
    }
}
