<?php

declare(strict_types=1);

namespace Pdf\Tests\Unit;

use Pdf\Builder\CoverLayout;
use Pdf\Document;
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

        $first = $doc->pages[0]->children;
        $headings = array_filter($first, static fn ($n) => $n instanceof Heading);
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

    public function test_each_layout_preset_produces_a_single_cover_page(): void
    {
        foreach (CoverLayout::cases() as $layout) {
            $doc = Document::create()
                ->cover(fn ($c) => $c->layout($layout)->title('T')->subtitle('S')->line('a', 'b'))
                ->build();

            self::assertCount(1, $doc->pages, $layout->name . ' should fit on one page');
        }
    }
}
