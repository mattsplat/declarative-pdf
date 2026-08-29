<?php

declare(strict_types=1);

namespace Pdf\Tests\Functional;

use Pdf\Document;
use Pdf\Exception\PdfException;
use Pdf\Tests\Support\Golden;
use Pdf\Tests\Support\Pdf;
use PHPUnit\Framework\TestCase;

final class BookmarkTest extends TestCase
{
    private function threePageOutline(): \Pdf\Builder\DocumentBuilder
    {
        return Document::create()->using(Pdf::deterministicRenderer())
            ->page(fn ($p) => $p
                ->anchor('intro')->heading(1, 'Introduction')
                ->paragraph('Opening remarks.'))
            ->page(fn ($p) => $p
                ->anchor('methods')->heading(1, 'Methods')
                ->paragraph('How it was done.')
                ->anchor('methods-data')->heading(2, 'Data sources')
                ->paragraph('Where the numbers came from.'))
            ->page(fn ($p) => $p
                ->anchor('results')->heading(1, 'Results')
                ->paragraph('What was found.'))
            ->bookmark('Introduction', 'intro')
            ->bookmark('Methods', 'methods')
            ->bookmark('Data sources', 'methods-data', 1)
            ->bookmark('Results', 'results');
    }

    public function test_nested_bookmarks_write_an_outline_tree(): void
    {
        $pdf = $this->threePageOutline()->toString();

        self::assertStringContainsString('/Type /Outlines', $pdf);
        self::assertStringContainsString('/Type /Catalog', $pdf);
        self::assertMatchesRegularExpression('#/Type /Catalog\n/Pages 1 0 R\n/Outlines \d+ 0 R#', $pdf);

        self::assertStringContainsString('/Title (Introduction)', $pdf);
        self::assertStringContainsString('/Title (Data sources)', $pdf);

        // The nested item carries a /Parent that is not the outline root, and
        // the parent item announces /First + /Last + /Count 1.
        self::assertMatchesRegularExpression('/\/Title \(Data sources\)\n\/Parent \d+ 0 R/', $pdf);
        self::assertMatchesRegularExpression('/\/Title \(Methods\)\n\/Parent \d+ 0 R\n\/Prev \d+ 0 R\n\/Next \d+ 0 R\n\/First \d+ 0 R\n\/Last \d+ 0 R\n\/Count 1/', $pdf);

        // Every item resolves to an explicit /XYZ destination.
        self::assertSame(4, preg_match_all('/\/Dest \[\d+ 0 R \/XYZ 0 [\d.]+ null\]/', $pdf));
    }

    public function test_outline_root_counts_every_open_item(): void
    {
        $pdf = $this->threePageOutline()->toString();

        self::assertMatchesRegularExpression('/\/Type \/Outlines\n\/First \d+ 0 R\n\/Last \d+ 0 R\n\/Count 4\n/', $pdf);
    }

    public function test_bookmark_targeting_an_unknown_anchor_throws(): void
    {
        $this->expectException(PdfException::class);
        $this->expectExceptionMessage('anchor "missing"');

        Document::create()->using(Pdf::deterministicRenderer())
            ->page(fn ($p) => $p->anchor('intro')->paragraph('hi'))
            ->bookmark('Nowhere', 'missing')
            ->toString();
    }

    public function test_a_document_without_bookmarks_has_no_outline(): void
    {
        $pdf = Document::create()->using(Pdf::deterministicRenderer())
            ->page(fn ($p) => $p->paragraph('plain'))
            ->toString();

        self::assertStringNotContainsString('/Outlines', $pdf);
    }

    public function test_bookmarks_golden(): void
    {
        Golden::assert('bookmarks.pdf', $this->threePageOutline()->toString());
    }
}
