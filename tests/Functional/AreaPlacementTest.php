<?php

declare(strict_types=1);

namespace Pdf\Tests\Functional;

use Pdf\Document;
use Pdf\Geometry\BoxAlign;
use Pdf\Geometry\Fit;
use Pdf\Geometry\PageSize;
use Pdf\Geometry\ShrinkMode;
use Pdf\Geometry\Unit;
use Pdf\Node\Paragraph;
use Pdf\Style\Border;
use Pdf\Style\StylePatch;
use Pdf\Tests\Support\Golden;
use Pdf\Tests\Support\Pdf;
use Pdf\Text\InlineSequence;
use PHPUnit\Framework\TestCase;

final class AreaPlacementTest extends TestCase
{
    private function fixture(string $name): string
    {
        return dirname(__DIR__) . '/fixtures/' . $name;
    }

    public function test_large_format_page_sizes(): void
    {
        $archE = PageSize::arch('e');
        self::assertEqualsWithDelta(2592.0, $archE->widthPt, 1e-6);
        self::assertEqualsWithDelta(3456.0, $archE->heightPt, 1e-6);

        $pdf = Document::create()
            ->using(Pdf::deterministicRenderer())
            ->page(fn ($p) => $p->size(PageSize::a0())->paragraph('A0 sheet'))
            ->toString();

        self::assertMatchesRegularExpression('/\/MediaBox \[0 0 2383\.94 3370\.39\]/', $pdf);
    }

    public function test_placed_image_is_positioned_and_scaled_by_fit(): void
    {
        $pdf = Document::create()
            ->using(Pdf::deterministicRenderer())
            ->page(fn ($p) => $p
                ->size(PageSize::a4())->units(Unit::Pt)
                ->placeImage(100, 100, 200, 200, $this->fixture('bar.jpg'), Fit::Contain, BoxAlign::Center))
            ->toString();

        $content = Pdf::contentText($pdf);
        // bar.jpg is 24x12 -> 2:1; Contain in 200x200 -> 200 x 100, vertically centred.
        self::assertMatchesRegularExpression('/q 200\.00 0 0 100\.00 100\.00 [\d.]+ cm \/I1 Do Q/', $content);
    }

    public function test_place_image_data_carries_bytes_inline(): void
    {
        $bytes = (string) file_get_contents($this->fixture('bar.jpg'));

        $pdf = Document::create()
            ->using(Pdf::deterministicRenderer())
            ->page(fn ($p) => $p
                ->size(PageSize::a4())->units(Unit::Pt)
                ->placeImageData(100, 100, 200, 200, $bytes, Fit::Contain, BoxAlign::Center))
            ->toString();

        // Same placement as the on-disk equivalent: 24x12 -> 2:1, Contain in 200 -> 200x100.
        self::assertMatchesRegularExpression('/q 200\.00 0 0 100\.00 100\.00 [\d.]+ cm \/I1 Do Q/', Pdf::contentText($pdf));
    }

    public function test_cover_fit_clips_the_image(): void
    {
        $pdf = Document::create()
            ->using(Pdf::deterministicRenderer())
            ->page(fn ($p) => $p
                ->units(Unit::Pt)
                ->placeImage(0, 0, 100, 100, $this->fixture('bar.jpg'), Fit::Cover))
            ->toString();

        $content = Pdf::contentText($pdf);
        self::assertMatchesRegularExpression('/q [\d.-]+ [\d.]+ 100\.00 100\.00 re W n/', $content, 'clip rect emitted');
    }

    public function test_placed_blocks_shrink_to_fit_the_area_height(): void
    {
        $pdf = Document::create()
            ->using(Pdf::deterministicRenderer())
            ->page(fn ($p) => $p
                ->units(Unit::Pt)
                // Four ~14pt lines (~60pt) squeezed into a 30pt-tall area -> ~0.5 scale.
                ->place(0, 0, 300, 30, [
                    new Paragraph("one\ntwo\nthree\nfour"),
                ]))
            ->toString();

        $content = Pdf::contentText($pdf);
        self::assertMatchesRegularExpression('/q 0\.[0-9]{3,4} 0 0 0\.[0-9]{3,4} [\d.]+ [\d.]+ cm/', $content);
        self::assertStringContainsString('(one) Tj', $content);
        self::assertStringContainsString('(four) Tj', $content);
    }

    public function test_font_size_shrink_mode_reflows_at_scale_one(): void
    {
        $pdf = Document::create()
            ->using(Pdf::deterministicRenderer())
            ->page(fn ($p) => $p
                ->units(Unit::Pt)
                // ~61pt of text (4 lines + spacing) into a 44pt area -> ~0.7x font.
                ->place(0, 0, 300, 44, [
                    new Paragraph("one\ntwo\nthree\nfour"),
                ], BoxAlign::TopLeft, ShrinkMode::FontSize))
            ->toString();

        $content = Pdf::contentText($pdf);

        // Drawn at 1:1 — no geometric scale wrap.
        self::assertStringContainsString('q 1.0000 0 0 1.0000', $content);
        self::assertDoesNotMatchRegularExpression('/q 0\.\d+ 0 0 0\.\d+ /', $content);

        // Effective font size dropped from 12pt into the ~7-9pt band.
        $sizes = self::fontSizes($content);
        self::assertNotEmpty($sizes);
        self::assertLessThan(10.0, max($sizes));
        self::assertGreaterThan(6.0, min($sizes));

        self::assertStringContainsString('(one) Tj', $content);
        self::assertStringContainsString('(four) Tj', $content);
    }

    public function test_font_size_shrink_mode_also_shrinks_a_hard_coded_inline_size(): void
    {
        $pdf = Document::create()
            ->using(Pdf::deterministicRenderer())
            ->page(fn ($p) => $p
                ->units(Unit::Pt)
                ->place(0, 0, 300, 20, [
                    new Paragraph(InlineSequence::of('')->withRun('BIG', new StylePatch(fontSizePt: 20.0))),
                ], BoxAlign::TopLeft, ShrinkMode::FontSize))
            ->toString();

        $content = Pdf::contentText($pdf);

        self::assertStringContainsString('q 1.0000 0 0 1.0000', $content);
        // The hard-coded 20pt run must have come down with everything else.
        self::assertLessThan(19.0, max(self::fontSizes($content)));
    }

    public function test_font_size_shrink_mode_respects_the_half_floor_and_falls_back_to_scale(): void
    {
        $pdf = Document::create()
            ->using(Pdf::deterministicRenderer())
            ->page(fn ($p) => $p
                ->units(Unit::Pt)
                // Far too tall for 0.5x to rescue -> geometric scale takes over.
                ->place(0, 0, 300, 8, [
                    new Paragraph("one\ntwo\nthree\nfour"),
                ], BoxAlign::TopLeft, ShrinkMode::FontSize))
            ->toString();

        $content = Pdf::contentText($pdf);
        self::assertMatchesRegularExpression('/q 0\.\d+ 0 0 0\.\d+ [\d.]+ [\d.]+ cm/', $content);
    }

    public function test_none_shrink_mode_leaves_content_at_natural_size(): void
    {
        $pdf = Document::create()
            ->using(Pdf::deterministicRenderer())
            ->page(fn ($p) => $p
                ->units(Unit::Pt)
                ->place(0, 0, 300, 8, [
                    new Paragraph("one\ntwo\nthree\nfour"),
                ], BoxAlign::TopLeft, ShrinkMode::None))
            ->toString();

        $content = Pdf::contentText($pdf);
        self::assertStringContainsString('q 1.0000 0 0 1.0000', $content);
        self::assertDoesNotMatchRegularExpression('/q 0\.\d+ 0 0 0\.\d+ /', $content);
        self::assertStringContainsString('(four) Tj', $content);
    }

    /** @return list<float> every font size set with a `Tf` operator */
    private static function fontSizes(string $content): array
    {
        preg_match_all('/\/F\d+ (\d+\.\d+) Tf/', $content, $m);

        return array_map('floatval', $m[1]);
    }

    public function test_frame_draws_a_border_rectangle(): void
    {
        $pdf = Document::create()
            ->using(Pdf::deterministicRenderer())
            ->page(fn ($p) => $p
                ->units(Unit::Pt)
                ->frame(10, 10, 400, 500, Border::uniform(2.0)))
            ->toString();

        $content = Pdf::contentText($pdf);
        // Four thin filled rectangles making up the border edges.
        self::assertGreaterThanOrEqual(4, preg_match_all('/ re f Q/', $content));
    }

    public function test_sheet_layout_is_byte_stable(): void
    {
        $pdf = Document::create()
            ->using(Pdf::deterministicRenderer())
            ->meta(fn ($m) => $m->title('Sheet'))
            ->page(function ($p) {
                $p->size(PageSize::arch('d'))->landscape()->units(Unit::In)->margin(0);
                $p->frame(0.5, 0.5, 35.0, 23.0, Border::uniform(1.5));
                $p->placeImage(1, 1, 26, 21, $this->fixture('bar.jpg'), Fit::Contain);
                $p->place(28, 1, 6, 10, [
                    new Paragraph('NOTES', new \Pdf\Style\StylePatch(bold: true)),
                    new Paragraph('Verify on site.'),
                ]);
            })
            ->toString();

        Golden::assert('sheet.pdf', $pdf);
    }
}
