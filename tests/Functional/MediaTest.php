<?php

declare(strict_types=1);

namespace Pdf\Tests\Functional;

use Pdf\Document;
use Pdf\Node\Paragraph;
use Pdf\Style\TextAlign;
use Pdf\Tests\Support\Golden;
use Pdf\Tests\Support\Pdf;
use Pdf\Text\InlineSequence;
use PHPUnit\Framework\TestCase;

final class MediaTest extends TestCase
{
    private function fixture(string $name): string
    {
        return dirname(__DIR__) . '/fixtures/' . $name;
    }

    public function test_alpha_image_bumps_pdf_version_and_adds_transparency_group(): void
    {
        $pdf = Document::create()
            ->using(Pdf::deterministicRenderer())
            ->page(fn ($p) => $p->image($this->fixture('dot-rgba.png'), width: 40.0))
            ->toString();

        self::assertStringStartsWith('%PDF-1.4', $pdf);
        self::assertStringContainsString('/Group <</Type /Group /S /Transparency', $pdf);
        self::assertStringContainsString('/SMask ', $pdf);
        self::assertStringContainsString('/XObject <<', $pdf);
        self::assertStringContainsString('/I1 ', $pdf);
        self::assertMatchesRegularExpression('/cm \/I1 Do Q/', Pdf::contentText($pdf));
    }

    public function test_opaque_jpeg_stays_pdf_1_3(): void
    {
        $pdf = Document::create()
            ->using(Pdf::deterministicRenderer())
            ->page(fn ($p) => $p->image($this->fixture('bar.jpg')))
            ->toString();

        self::assertStringStartsWith('%PDF-1.3', $pdf);
        self::assertStringContainsString('/Filter /DCTDecode', $pdf);
        self::assertStringNotContainsString('/Group', $pdf);
    }

    public function test_external_and_internal_links_become_annotations(): void
    {
        $pdf = Document::create()
            ->using(Pdf::deterministicRenderer())
            ->page(function ($p) {
                $p->anchor('target');
                $p->heading(2, 'Target section');
                for ($i = 0; $i < 20; $i++) {
                    $p->paragraph(str_repeat('Padding line to push things down. ', 8));
                }
                $p->paragraph(
                    InlineSequence::of('Go to ')
                        ->withLink('the web', 'https://example.org/docs')
                        ->withRun(' or ')
                        ->withLink('the target', '#target')
                        ->withRun('.'),
                );
            })
            ->toString();

        self::assertStringContainsString('/Subtype /Link', $pdf);
        self::assertStringContainsString('/A <</S /URI /URI (https://example.org/docs)>>', $pdf);
        self::assertMatchesRegularExpression('/\/Dest \[\d+ 0 R \/XYZ 0 [\d.]+ null\]/', $pdf);
    }

    public function test_missing_internal_anchor_produces_a_dead_but_valid_annotation(): void
    {
        $pdf = Document::create()
            ->using(Pdf::deterministicRenderer())
            ->page(fn ($p) => $p->paragraph(
                InlineSequence::of('Broken ')->withLink('link', '#nowhere'),
            ))
            ->toString();

        self::assertStringContainsString('/Subtype /Link', $pdf);
        self::assertStringNotContainsString('/Dest', $pdf);
    }

    public function test_columns_and_media_document_is_byte_stable(): void
    {
        $pdf = Document::create()
            ->using(Pdf::deterministicRenderer())
            ->meta(fn ($m) => $m->title('Media'))
            ->page(function ($p) {
                $p->heading(1, 'Media');
                $p->image($this->fixture('bar.jpg'), width: 30.0, align: TextAlign::Center);
                $p->paragraph(
                    InlineSequence::of('See ')->withLink('example', 'https://example.com'),
                );
                $p->columns([
                    new Paragraph(str_repeat('Left column body text that wraps. ', 10)),
                    new Paragraph(str_repeat('Right column body text that wraps. ', 10)),
                ], count: 2);
            })
            ->toString();

        Golden::assert('media.pdf', $pdf);
    }
}
