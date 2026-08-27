<?php

declare(strict_types=1);

namespace Pdf\Tests\Unit;

use Pdf\Image\ImageFactory;
use PHPUnit\Framework\TestCase;

final class ImageDecoderTest extends TestCase
{
    private function fixture(string $name): string
    {
        return dirname(__DIR__) . '/fixtures/' . $name;
    }

    public function test_decodes_a_jpeg(): void
    {
        $image = (new ImageFactory())->fromPath($this->fixture('bar.jpg'));

        self::assertSame(24, $image->widthPx);
        self::assertSame(12, $image->heightPx);
        self::assertSame('DeviceRGB', $image->colorSpace);
        self::assertSame('DCTDecode', $image->filter);
        self::assertFalse($image->hasAlpha());
        self::assertFalse($image->requiresPdf14);
    }

    public function test_decodes_an_rgba_png_into_colour_plus_soft_mask(): void
    {
        $image = (new ImageFactory())->fromPath($this->fixture('dot-rgba.png'));

        self::assertSame(20, $image->widthPx);
        self::assertSame(16, $image->heightPx);
        self::assertSame('DeviceRGB', $image->colorSpace);
        self::assertSame('FlateDecode', $image->filter);
        self::assertTrue($image->hasAlpha(), 'alpha channel becomes a soft mask');
        self::assertTrue($image->requiresPdf14);
        // Soft mask inflates to width*height grayscale bytes plus one filter byte per row.
        self::assertNotNull($image->softMask);
        self::assertSame(20 * 16 + 16, strlen((string) gzuncompress((string) $image->softMask)));
    }

    public function test_decodes_a_gif_via_png(): void
    {
        $image = (new ImageFactory())->fromPath($this->fixture('square.gif'));

        self::assertSame(10, $image->widthPx);
        self::assertSame(10, $image->heightPx);
        self::assertContains($image->colorSpace, ['Indexed', 'DeviceRGB']);
        self::assertSame('FlateDecode', $image->filter);
    }

    public function test_caches_by_path(): void
    {
        $factory = new ImageFactory();

        self::assertSame(
            $factory->fromPath($this->fixture('bar.jpg')),
            $factory->fromPath($this->fixture('bar.jpg')),
        );
    }
}
