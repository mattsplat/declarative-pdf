<?php

declare(strict_types=1);

namespace Pdf\Render;

use Pdf\Image\ImageResource;

/**
 * Writes image XObjects into the PDF.
 *
 * Ported from `_putimages()` / `_putimage()` (fpdf.php:1822-1875): the image
 * dictionary, indexed colour space with a palette stream object, colour-key
 * `/Mask`, and a recursive DeviceGray `/SMask` sub-image for alpha.
 */
final class ImageWriter
{
    public function __construct(private readonly PdfWriter $writer)
    {
    }

    /** @param list<ResolvedImage> $images */
    public function write(array $images): void
    {
        foreach ($images as $image) {
            $image->objectNumber = $this->writeResource($image->resource);
        }
    }

    private function writeResource(ImageResource $r): int
    {
        $object = $this->writer->beginObject();
        $this->writer->line('<</Type /XObject');
        $this->writer->line('/Subtype /Image');
        $this->writer->line('/Width ' . $r->widthPx);
        $this->writer->line('/Height ' . $r->heightPx);

        if ($r->colorSpace === 'Indexed') {
            $paletteEntries = intdiv(strlen((string) $r->palette), 3) - 1;
            $this->writer->line('/ColorSpace [/Indexed /DeviceRGB ' . $paletteEntries . ' ' . ($object + 1) . ' 0 R]');
        } else {
            $this->writer->line('/ColorSpace /' . $r->colorSpace);
            if ($r->colorSpace === 'DeviceCMYK') {
                $this->writer->line('/Decode [1 0 1 0 1 0 1 0]');
            }
        }

        $this->writer->line('/BitsPerComponent ' . $r->bitsPerComponent);
        if ($r->filter !== null) {
            $this->writer->line('/Filter /' . $r->filter);
        }
        if ($r->decodeParms !== null) {
            $this->writer->line('/DecodeParms <<' . $r->decodeParms . '>>');
        }
        if ($r->colorKeyMask !== []) {
            $mask = [];
            foreach ($r->colorKeyMask as $value) {
                $mask[] = $value . ' ' . $value;
            }
            $this->writer->line('/Mask [' . implode(' ', $mask) . ']');
        }
        if ($r->softMask !== null) {
            $this->writer->line('/SMask ' . ($object + 1) . ' 0 R');
        }
        $this->writer->line('/Length ' . strlen($r->data) . '>>');
        $this->writer->stream($r->data);
        $this->writer->endObject();

        if ($r->softMask !== null) {
            $this->writeSoftMask($r->widthPx, $r->heightPx, $r->filter, $r->softMask);
        }
        if ($r->colorSpace === 'Indexed' && $r->palette !== null) {
            $this->writer->streamObject($r->palette);
        }

        return $object;
    }

    private function writeSoftMask(int $width, int $height, ?string $filter, string $data): void
    {
        $this->writer->beginObject();
        $this->writer->line('<</Type /XObject /Subtype /Image');
        $this->writer->line('/Width ' . $width);
        $this->writer->line('/Height ' . $height);
        $this->writer->line('/ColorSpace /DeviceGray');
        $this->writer->line('/BitsPerComponent 8');
        if ($filter !== null) {
            $this->writer->line('/Filter /' . $filter);
        }
        $this->writer->line('/DecodeParms <</Predictor 15 /Colors 1 /BitsPerComponent 8 /Columns ' . $width . '>>');
        $this->writer->line('/Length ' . strlen($data) . '>>');
        $this->writer->stream($data);
        $this->writer->endObject();
    }
}
