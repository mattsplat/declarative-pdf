<?php

declare(strict_types=1);

namespace Pdf\Tests\Functional;

use Pdf\Document;
use Pdf\Style\StylePatch;
use Pdf\Style\TextAlign;
use Pdf\Tests\Support\Golden;
use Pdf\Tests\Support\Pdf;
use Pdf\Text\InlineSequence;
use PHPUnit\Framework\TestCase;

final class RenderTest extends TestCase
{
    private function sampleTree(): \Pdf\Node\Document
    {
        return Document::create()
            ->meta(fn ($m) => $m->title('Sample')->author('Test Suite'))
            ->page(fn ($p) => $p
                ->heading(1, 'Sample Document')
                ->paragraph('The quick brown fox jumps over the lazy dog. '
                    . 'This sentence is long enough to wrap onto a second line when '
                    . 'placed inside the default A4 content width.')
                ->paragraph(
                    InlineSequence::of('It also mixes ')
                        ->withRun('bold', new StylePatch(bold: true))
                        ->withRun(' and ')
                        ->withRun('italic', new StylePatch(italic: true))
                        ->withRun(' text, and it is justified so that inter-word spacing is '
                            . 'stretched to fill every line except the last one, exactly the '
                            . 'way MultiCell did it in the original library.'),
                    new StylePatch(align: TextAlign::Justify),
                ))
            ->build();
    }

    public function test_renders_a_structurally_valid_pdf(): void
    {
        $pdf = Pdf::deterministicRenderer()->render($this->sampleTree());

        self::assertStringStartsWith('%PDF-1.3', $pdf);
        self::assertStringEndsWith("%%EOF\n", $pdf);
        self::assertStringContainsString('/Type /Catalog', $pdf);
        self::assertStringContainsString('/Type /Pages', $pdf);
        self::assertStringContainsString('/Producer (fpdf/pdf-test)', $pdf);
        self::assertStringContainsString("/CreationDate (D:20260826120000+00'00')", $pdf);
    }

    public function test_xref_size_matches_object_count(): void
    {
        $pdf = Pdf::deterministicRenderer()->render($this->sampleTree());

        self::assertSame(1, preg_match('/\/Size (\d+)/', $pdf, $size));
        $objects = Pdf::objectNumbers($pdf);

        // /Size is highest object number + 1; object 1 and 2 are reserved.
        self::assertSame(max($objects) + 1, (int) $size[1]);
        self::assertContains(1, $objects, 'pages tree object 1 is present');
        self::assertContains(2, $objects, 'resource dictionary object 2 is present');
    }

    public function test_content_stream_carries_the_text(): void
    {
        $pdf = Pdf::deterministicRenderer()->render($this->sampleTree());
        $content = Pdf::contentText($pdf);

        self::assertStringContainsString('(Sample Document) Tj', $content);
        self::assertStringContainsString('quick brown fox', $content);
        self::assertStringContainsString('Tw ', $content, 'justified line emits word spacing');
        self::assertStringContainsString('/F1 24.00 Tf', $content, 'h1 uses 2x base size');
    }

    public function test_output_is_byte_for_byte_stable(): void
    {
        $pdf = Pdf::deterministicRenderer()->render($this->sampleTree());

        Golden::assert('sample.pdf', $pdf);
    }

    public function test_builder_save_writes_a_file(): void
    {
        $path = sys_get_temp_dir() . '/fpdf-pdf-' . uniqid() . '.pdf';

        Document::create()
            ->using(Pdf::deterministicRenderer())
            ->page(fn ($p) => $p->paragraph('hello'))
            ->save($path);

        self::assertFileExists($path);
        self::assertStringStartsWith('%PDF-', (string) file_get_contents($path));
        unlink($path);
    }
}
