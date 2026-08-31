<?php

declare(strict_types=1);

namespace Pdf\Tests\Unit;

use Pdf\Builder\CoverLayout;
use Pdf\Document;
use Pdf\Geometry\Orientation;
use Pdf\Geometry\PageSize;
use Pdf\Node\Container;
use Pdf\Node\Heading;
use PHPUnit\Framework\TestCase;

final class CoverBuilderTest extends TestCase
{
    public function test_cover_prepends_a_page_ahead_of_the_body(): void
    {
        $doc = Document::create()
            ->page(fn ($p) => $p->paragraph('body page'))
            ->cover(fn ($c) => $c->title('The Cover'))
            ->build();

        self::assertCount(2, $doc->pages);

        $headings = array_filter($doc->pages[0]->children, static fn ($n) => $n instanceof Heading);
        self::assertNotEmpty($headings, 'the cover page should carry the title heading');
    }

    public function test_the_bottom_band_layout_emits_a_backgrounded_container(): void
    {
        $doc = Document::create()
            ->cover(fn ($c) => $c
                ->layout(CoverLayout::BottomBand)
                ->title('Banded')
                ->subtitle('with a wash')
                ->line('2026'))
            ->build();

        $containers = array_filter(
            $doc->pages[0]->children,
            static fn ($n) => $n instanceof Container && $n->patch()->background !== null,
        );
        self::assertCount(1, $containers);
    }

    public function test_every_preset_fits_one_page_at_a4_letter_landscape_and_a5(): void
    {
        $geometries = [
            [PageSize::a4(), Orientation::Portrait],
            [PageSize::letter(), Orientation::Landscape],
            [PageSize::a5(), Orientation::Portrait],
        ];

        foreach (CoverLayout::cases() as $layout) {
            foreach ($geometries as [$size, $orientation]) {
                $doc = Document::create()
                    ->cover(fn ($c) => $c
                        ->layout($layout)
                        ->size($size)
                        ->orientation($orientation)
                        ->title('Title')
                        ->subtitle('A subtitle that is reasonably long so it wraps on a narrow sheet')
                        ->line('date', 'author', 'ref'))
                    ->build();

                self::assertCount(1, $doc->pages, "{$layout->name} overflowed at {$size->widthPt}x{$size->heightPt}");
            }
        }
    }

    public function test_the_cover_page_takes_the_configured_size_and_orientation(): void
    {
        $doc = Document::create()
            ->cover(fn ($c) => $c->size(PageSize::letter())->landscape()->title('Wide'))
            ->build();

        $master = $doc->pages[0]->master;
        self::assertSame(Orientation::Landscape, $master->orientation);
        self::assertTrue($master->size->equals(PageSize::letter()));
    }
}
