<?php

declare(strict_types=1);

namespace Pdf\Tests\Functional;

use Pdf\Document;
use Pdf\Geometry\BoxAlign;
use Pdf\Geometry\Fit;
use Pdf\Geometry\PageSize;
use Pdf\Geometry\Unit;
use Pdf\Import\PdfImportDocument;
use Pdf\Import\PdfReader;
use Pdf\Node\Paragraph;
use Pdf\Tests\Support\Golden;
use Pdf\Tests\Support\Pdf;
use PHPUnit\Framework\TestCase;

final class PdfImportTest extends TestCase
{
    private string $source = '';
    private string $multiPage = '';

    protected function setUp(): void
    {
        $renderer = Pdf::deterministicRenderer();

        $this->source = tempnam(sys_get_temp_dir(), 'src') . '.pdf';
        Document::create()->using($renderer)
            ->page(fn ($p) => $p->heading(1, 'Source Document')->paragraph('Imported content lives here.'))
            ->save($this->source);

        $this->multiPage = tempnam(sys_get_temp_dir(), 'multi') . '.pdf';
        Document::create()->using($renderer)
            ->page(function ($p) {
                for ($i = 1; $i <= 3; $i++) {
                    $p->heading(2, "Chapter {$i}");
                    for ($j = 0; $j < 12; $j++) {
                        $p->paragraph(str_repeat("Body of chapter {$i}. ", 20));
                    }
                }
            })
            ->save($this->multiPage);
    }

    protected function tearDown(): void
    {
        @unlink($this->source);
        @unlink($this->multiPage);
    }

    public function test_reader_parses_our_own_output(): void
    {
        $doc = new PdfImportDocument(PdfReader::fromFile($this->source));

        self::assertSame(1, $doc->pageCount());
        $page = $doc->page(1);
        self::assertEqualsWithDelta(595.28, $page->boxWidthPt(), 1e-2);
        self::assertEqualsWithDelta(841.89, $page->boxHeightPt(), 1e-2);
        self::assertSame(0, $page->rotation);
        self::assertStringContainsString('(Source Document) Tj', $page->contentBytes);
        self::assertNotEmpty($page->dependencies);
    }

    public function test_multi_page_source_page_selection(): void
    {
        $doc = new PdfImportDocument(PdfReader::fromFile($this->multiPage));
        self::assertGreaterThan(1, $doc->pageCount());

        $page2 = $doc->page(2);
        self::assertStringNotContainsString('(Chapter 1) Tj', $page2->contentBytes);
    }

    public function test_placed_pdf_becomes_a_deduplicated_form_xobject(): void
    {
        $pdf = Document::create()->using(Pdf::deterministicRenderer())
            ->page(fn ($p) => $p
                ->size(PageSize::a4())->landscape()->units(Unit::Pt)
                ->placePdf(20, 20, 380, 555, $this->source, 1, Fit::Contain)
                ->placePdf(410, 20, 380, 555, $this->source, 1, Fit::Contain, BoxAlign::TopLeft))
            ->toString();

        self::assertStringStartsWith('%PDF-1.4', $pdf);
        self::assertSame(1, substr_count($pdf, '/Subtype /Form'), 'the two placements share one form');
        // Drawn twice ...
        self::assertSame(2, substr_count(Pdf::contentText($pdf), '/Import1 Do'));
        // ... but the imported content (with its fonts) is stored once.
        self::assertSame(1, substr_count(Pdf::contentText($pdf), '(Source Document) Tj'));
    }

    public function test_nested_image_resources_survive_the_import(): void
    {
        $withImage = tempnam(sys_get_temp_dir(), 'img') . '.pdf';
        Document::create()->using(Pdf::deterministicRenderer())
            ->page(fn ($p) => $p
                ->heading(2, 'Has an image')
                ->image(dirname(__DIR__) . '/fixtures/dot-rgba.png', width: 20.0))
            ->save($withImage);

        $pdf = Document::create()->using(Pdf::deterministicRenderer())
            ->page(fn ($p) => $p->size(PageSize::a4())->units(Unit::Pt)
                ->placePdf(40, 40, 515, 760, $withImage, 1))
            ->toString();

        @unlink($withImage);

        self::assertStringContainsString('/Subtype /Image', $pdf);
        self::assertStringContainsString('/SMask ', $pdf, 'the RGBA image soft mask is carried through');
        self::assertStringContainsString('(Has an image) Tj', Pdf::contentText($pdf));
    }

    public function test_imports_a_pdf_with_a_predicted_cross_reference_stream(): void
    {
        $pdf = self::xrefStreamPdf();
        $doc = new PdfImportDocument(new PdfReader($pdf));

        self::assertSame(1, $doc->pageCount());
        $page = $doc->page(1);
        self::assertSame([0.0, 0.0, 200.0, 100.0], $page->boundingBox);
        self::assertStringContainsString('re f', $page->contentBytes);
    }

    public function test_indirect_stream_length_is_resolved(): void
    {
        // /Length is an indirect reference; the deflate output also contains the
        // bytes "endstream", so a naive scan would truncate it.
        $content = (string) gzcompress(str_repeat('q Q endstream ', 40));
        $body = "%PDF-1.4\n";
        $offsets = [];
        $offsets[1] = strlen($body);
        $body .= "1 0 obj\n<< /Type /Catalog /Pages 2 0 R >>\nendobj\n";
        $offsets[2] = strlen($body);
        $body .= "2 0 obj\n<< /Type /Pages /Kids [3 0 R] /Count 1 >>\nendobj\n";
        $offsets[3] = strlen($body);
        $body .= "3 0 obj\n<< /Type /Page /Parent 2 0 R /MediaBox [0 0 50 50] /Contents 4 0 R /Resources << >> >>\nendobj\n";
        $offsets[4] = strlen($body);
        $body .= "4 0 obj\n<< /Length 5 0 R /Filter /FlateDecode >>\nstream\n{$content}\nendstream\nendobj\n";
        $offsets[5] = strlen($body);
        $body .= "5 0 obj\n" . strlen($content) . "\nendobj\n";
        $xref = strlen($body);
        $body .= "xref\n0 6\n0000000000 65535 f \n";
        foreach ([1, 2, 3, 4, 5] as $n) {
            $body .= sprintf("%010d 00000 n \n", $offsets[$n]);
        }
        $body .= "trailer\n<< /Root 1 0 R /Size 6 >>\nstartxref\n{$xref}\n%%EOF";

        $page = (new PdfImportDocument(new PdfReader($body)))->page(1);

        self::assertSame(str_repeat('q Q endstream ', 40), $page->contentBytes);
    }

    public function test_bad_page_tree_fails_loud(): void
    {
        $body = "%PDF-1.4\n";
        $o1 = strlen($body);
        $body .= "1 0 obj\n<< /Type /Catalog /Pages 2 0 R >>\nendobj\n";
        $o2 = strlen($body);
        // /Kids points at object 9, which does not exist.
        $body .= "2 0 obj\n<< /Type /Pages /Kids 9 0 R /Count 1 >>\nendobj\n";
        $xref = strlen($body);
        $body .= "xref\n0 3\n0000000000 65535 f \n";
        $body .= sprintf("%010d 00000 n \n", $o1);
        $body .= sprintf("%010d 00000 n \n", $o2);
        $body .= "trailer\n<< /Root 1 0 R /Size 3 >>\nstartxref\n{$xref}\n%%EOF";

        $this->expectExceptionMessage('unreadable /Kids');
        new PdfImportDocument(new PdfReader($body));
    }

    private static function xrefStreamPdf(): string
    {
        $objects = [
            1 => '<< /Type /Catalog /Pages 2 0 R >>',
            2 => '<< /Type /Pages /Kids [3 0 R] /Count 1 >>',
            3 => '<< /Type /Page /Parent 2 0 R /MediaBox [0 0 200 100] /Contents 4 0 R /Resources << >> >>',
        ];
        $content = '0 0 1 rg 10 10 50 30 re f';

        $body = "%PDF-1.5\n";
        $offsets = [];
        foreach ($objects as $n => $dict) {
            $offsets[$n] = strlen($body);
            $body .= "{$n} 0 obj\n{$dict}\nendobj\n";
        }
        $offsets[4] = strlen($body);
        $body .= "4 0 obj\n<< /Length " . strlen($content) . " >>\nstream\n{$content}\nendstream\nendobj\n";
        $offsets[5] = strlen($body);

        // Cross-reference rows for objects 0..5, W = [1, 2, 1].
        $rows = "\x00" . pack('n', 0) . "\xFF"; // obj 0: free
        foreach ([1, 2, 3, 4, 5] as $n) {
            $rows .= "\x01" . pack('n', $offsets[$n]) . "\x00";
        }

        // PNG "Up" prediction, 4-byte rows, filter byte 0x02 per row.
        $predicted = '';
        $previous = str_repeat("\x00", 4);
        foreach (str_split($rows, 4) as $row) {
            $diff = '';
            for ($i = 0; $i < 4; $i++) {
                $diff .= chr((ord($row[$i]) - ord($previous[$i])) & 0xFF);
            }
            $predicted .= "\x02" . $diff;
            $previous = $row;
        }
        $stream = (string) gzcompress($predicted);

        $dict = '<< /Type /XRef /Size 6 /Root 1 0 R /W [1 2 1]'
            . ' /Filter /FlateDecode /DecodeParms << /Predictor 12 /Columns 4 >>'
            . ' /Length ' . strlen($stream) . ' >>';
        $body .= "5 0 obj\n{$dict}\nstream\n{$stream}\nendstream\nendobj\n";
        $body .= "startxref\n{$offsets[5]}\n%%EOF";

        return $body;
    }

    public function test_encrypted_pdf_is_rejected(): void
    {
        // Minimal but well-formed classic-xref file whose trailer has /Encrypt.
        $body = "%PDF-1.4\n";
        $o1 = strlen($body);
        $body .= "1 0 obj\n<< /Type /Catalog /Pages 2 0 R >>\nendobj\n";
        $o2 = strlen($body);
        $body .= "2 0 obj\n<< /Type /Pages /Kids [] /Count 0 >>\nendobj\n";
        $xrefAt = strlen($body);
        $body .= "xref\n0 3\n";
        $body .= "0000000000 65535 f \n";
        $body .= sprintf("%010d 00000 n \n", $o1);
        $body .= sprintf("%010d 00000 n \n", $o2);
        $body .= "trailer\n<< /Root 1 0 R /Size 3 /Encrypt 3 0 R >>\n";
        $body .= "startxref\n{$xrefAt}\n%%EOF";

        $fake = tempnam(sys_get_temp_dir(), 'enc') . '.pdf';
        file_put_contents($fake, $body);

        try {
            $this->expectExceptionMessage('Encrypted');
            PdfReader::fromFile($fake);
        } finally {
            @unlink($fake);
        }
    }

    public function test_placed_pdf_document_is_byte_stable(): void
    {
        $pdf = Document::create()->using(Pdf::deterministicRenderer())
            ->meta(fn ($m) => $m->title('Assembly'))
            ->page(fn ($p) => $p
                ->size(PageSize::a3())->landscape()->units(Unit::Mm)->margin(0)
                ->frame(10, 10, 400, 277, \Pdf\Style\Border::uniform(1.0))
                ->placePdf(15, 15, 260, 267, $this->source, 1, Fit::Contain)
                ->place(285, 15, 115, 120, [new Paragraph('Assembled from an imported page.')]))
            ->toString();

        Golden::assert('imported.pdf', $pdf);
    }
}
