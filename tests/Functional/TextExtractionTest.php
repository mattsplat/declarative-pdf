<?php

declare(strict_types=1);

namespace Pdf\Tests\Functional;

use Pdf\Document;
use Pdf\Import\PdfImportDocument;
use Pdf\Import\TextExtractor;
use Pdf\Node\Table;
use Pdf\Node\TableCell;
use Pdf\Node\TableRow;
use Pdf\Style\ColumnWidth;
use Pdf\Tests\Support\Pdf;
use PHPUnit\Framework\TestCase;

final class TextExtractionTest extends TestCase
{
    private string $path = '';

    protected function tearDown(): void
    {
        @unlink($this->path);
    }

    public function test_extracts_a_heading_and_a_paragraph(): void
    {
        $this->save(fn ($p) => $p
            ->heading(1, 'Quarterly Review')
            ->paragraph('Revenue grew across every region this quarter.'));

        $text = TextExtractor::fromFile($this->path)->text();

        self::assertStringContainsString('Quarterly Review', $text);
        self::assertStringContainsString('Revenue grew across every region this quarter.', $text);
    }

    public function test_a_wrapped_paragraph_comes_back_as_readable_lines(): void
    {
        $this->save(fn ($p) => $p->paragraph(str_repeat('word ', 60)));

        $text = TextExtractor::fromFile($this->path)->text();

        // Every line breaker split rejoins as "word word word …", never "wordword".
        self::assertMatchesRegularExpression('/^(word ?\n?)+$/', trim($text));
        self::assertStringNotContainsString('wordword', $text);
    }

    public function test_pages_returns_one_entry_per_page_in_order(): void
    {
        Document::create()->using(Pdf::deterministicRenderer())
            ->page(fn ($p) => $p->paragraph('First page.'))
            ->page(fn ($p) => $p->paragraph('Second page.'))
            ->save($this->path = tempnam(sys_get_temp_dir(), 'extract') . '.pdf');

        $pages = TextExtractor::fromFile($this->path)->pages();

        self::assertCount(2, $pages);
        self::assertStringContainsString('First page.', $pages[0]);
        self::assertStringContainsString('Second page.', $pages[1]);
        self::assertStringNotContainsString('Second page.', $pages[0]);
    }

    public function test_table_cells_on_one_row_stay_space_separated(): void
    {
        $this->save(fn ($p) => $p->add(new Table(
            [
                new TableRow([new TableCell('Line'), new TableCell('Q2'), new TableCell('Q3')]),
                new TableRow([new TableCell('Revenue'), new TableCell('12.4'), new TableCell('13.9')]),
            ],
            [ColumnWidth::fraction(1.0), ColumnWidth::fixed(60.0), ColumnWidth::fixed(60.0)],
            headerRows: 1,
        )));

        $text = TextExtractor::fromFile($this->path)->text();

        self::assertStringContainsString('Line', $text);
        self::assertStringContainsString('Revenue', $text);
        // Cells are drawn as separate text-showing operators; the position-based
        // gap heuristic must still land a space between "Revenue" and "12.4".
        self::assertMatchesRegularExpression('/Revenue\s+12\.4\s+13\.9/', $text);
    }

    public function test_imported_page_extract_text_matches_the_extractor(): void
    {
        $this->save(fn ($p) => $p->paragraph('Delegated through ImportedPage.'));

        $doc = PdfImportDocument::fromFile($this->path);

        self::assertSame(TextExtractor::extractPage($doc->page(1)), $doc->page(1)->extractText());
        self::assertStringContainsString('Delegated through ImportedPage.', $doc->page(1)->extractText());
    }

    /** @param \Closure(\Pdf\Builder\PageBuilder): void $body */
    private function save(\Closure $body): void
    {
        $this->path = tempnam(sys_get_temp_dir(), 'extract') . '.pdf';
        Document::create()->using(Pdf::deterministicRenderer())
            ->page($body)
            ->save($this->path);
    }
}
