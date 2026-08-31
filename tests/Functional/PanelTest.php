<?php

declare(strict_types=1);

namespace Pdf\Tests\Functional;

use Pdf\Builder\Panel;
use Pdf\Builder\PageBuilder;
use Pdf\Document;
use Pdf\Geometry\BoxAlign;
use Pdf\Geometry\Fit;
use Pdf\Geometry\PageSize;
use Pdf\Geometry\Rect;
use Pdf\Geometry\Unit;
use Pdf\Node\Paragraph;
use Pdf\Style\Border;
use Pdf\Tests\Support\Pdf;
use PHPUnit\Framework\TestCase;

final class PanelTest extends TestCase
{
    private string $source = '';

    protected function setUp(): void
    {
        $this->source = tempnam(sys_get_temp_dir(), 'panel') . '.pdf';
        Document::create()->using(Pdf::deterministicRenderer())
            ->page(fn ($p) => $p->heading(1, 'Panel Source')->paragraph('Framed content.'))
            ->save($this->source);
    }

    protected function tearDown(): void
    {
        @unlink($this->source);
    }

    public function test_panel_draws_a_frame_and_insets_the_placed_page(): void
    {
        $pdf = Document::create()->using(Pdf::deterministicRenderer())
            ->page(function (PageBuilder $p): void {
                $p->size(PageSize::a4())->units(Unit::Pt)->margin(0);
                Panel::at(100, 100, 200, 200)
                    ->showing($this->source)
                    ->inset(10)
                    ->drawOn($p);
            })
            ->toString();

        $content = Pdf::contentText($pdf);

        // The frame draws its four edges as thin filled rectangles. The bottom
        // edge spans the full 200pt width at (100, 100) — flipped to a
        // bottom-left origin, that y is 841.89 - 300 = 541.89.
        self::assertMatchesRegularExpression('/100\.00 541\.89 200\.00 0\.75 re/', $content);
        // The source page (A4, 595.28 x 841.89) is placed once, contain-fitted
        // into the 180x180 inset area: min(180/595.28, 180/841.89) = 0.21380.
        self::assertSame(1, substr_count($content, '/Import1 Do'));
        self::assertMatchesRegularExpression('/0\.21380 0\.00000 0\.00000 0\.21380 .* cm \/Import1 Do/', $content);
    }

    public function test_framed_overrides_the_default_border(): void
    {
        $pdf = Document::create()->using(Pdf::deterministicRenderer())
            ->page(function (PageBuilder $p): void {
                $p->size(PageSize::a4())->units(Unit::Pt)->margin(0);
                Panel::at(20, 20, 100, 100)
                    ->showing($this->source)
                    ->framed(Border::uniform(4.0))
                    ->drawOn($p);
            })
            ->toString();

        // Border::uniform(4.0) thickens each frame edge to 4pt: the top/bottom
        // edges become 100pt-wide, 4pt-tall filled rectangles.
        self::assertMatchesRegularExpression('/20\.00 [\d.]+ 100\.00 4\.00 re f/', Pdf::contentText($pdf));
    }

    public function test_draw_on_before_showing_is_rejected(): void
    {
        $this->expectException(\LogicException::class);

        Document::create()->using(Pdf::deterministicRenderer())
            ->page(function (PageBuilder $p): void {
                $p->size(PageSize::a4());
                Panel::at(0, 0, 10, 10)->drawOn($p);
            })
            ->toString();
    }

    public function test_configuration_methods_return_new_instances(): void
    {
        $base = Panel::at(0, 0, 10, 10);

        self::assertNotSame($base, $base->showing('x.pdf'));
        self::assertNotSame($base, $base->containing([new Paragraph('x')]));
        self::assertNotSame($base, $base->inset(5));
        self::assertNotSame($base, $base->fitted(Fit::Cover, BoxAlign::TopLeft));
        self::assertNotSame($base, $base->aligned(BoxAlign::Center));
        self::assertNotSame($base, $base->framed(Border::uniform(1.0)));
    }

    public function test_in_takes_a_point_rect_and_converts_to_the_page_unit(): void
    {
        $pdf = Document::create()->using(Pdf::deterministicRenderer())
            ->page(function (PageBuilder $p): void {
                $p->size(PageSize::a4())->units(Unit::Mm)->margin(0);
                // 72pt == 1in == 25.4mm; a 144pt-wide panel at (72, 72)pt.
                Panel::in(new Rect(72, 72, 144, 144))
                    ->containing([new Paragraph('in points')])
                    ->drawOn($p);
            })
            ->toString();

        // Bottom frame edge spans 144pt at x=72pt; flipped y = 841.89 - 216 = 625.89.
        self::assertMatchesRegularExpression('/72\.00 625\.89 144\.00 0\.75 re/', Pdf::contentText($pdf));
    }

    public function test_inset_on_a_point_space_panel_is_measured_in_points(): void
    {
        $pdf = Document::create()->using(Pdf::deterministicRenderer())
            ->page(function (PageBuilder $p): void {
                // A millimetre page: a naive inset would be read as 20mm (~57pt).
                $p->size(PageSize::a4())->units(Unit::Mm)->margin(0);
                Panel::in(new Rect(0, 0, 200, 200))
                    ->inset(20)
                    ->showing($this->source)
                    ->drawOn($p);
            })
            ->toString();

        // 20pt inset -> a 160x160pt inner area; the A4 source contain-fits at
        // 160 / 841.89 = 0.19005. A 20mm inset would scale by ~0.10.
        self::assertMatchesRegularExpression(
            '/0\.19005 0\.00000 0\.00000 0\.19005 .* cm \/Import1 Do/',
            Pdf::contentText($pdf),
        );
    }

    public function test_block_content_defaults_to_top_left_alignment(): void
    {
        $render = function (?BoxAlign $align): string {
            $panel = Panel::at(40, 40, 320, 260)->containing([new Paragraph('Short.')]);
            if ($align !== null) {
                $panel = $panel->aligned($align);
            }

            return Pdf::contentText(
                Document::create()->using(Pdf::deterministicRenderer())
                    ->page(function (PageBuilder $p) use ($panel): void {
                        $p->size(PageSize::a4())->units(Unit::Pt)->margin(0);
                        $panel->drawOn($p);
                    })
                    ->toString(),
            );
        };

        self::assertSame($render(null), $render(BoxAlign::TopLeft), 'default matches TopLeft');
        self::assertNotSame($render(null), $render(BoxAlign::Center), 'Center shifts the content');
    }

    public function test_showing_dispatches_by_extension_ignoring_case_and_query_string(): void
    {
        $upper = tempnam(sys_get_temp_dir(), 'panel') . '.PDF';
        copy($this->source, $upper);

        try {
            $pdf = Document::create()->using(Pdf::deterministicRenderer())
                ->page(function (PageBuilder $p) use ($upper): void {
                    $p->size(PageSize::a4())->units(Unit::Pt)->margin(0);
                    Panel::at(20, 20, 200, 200)->showing($upper)->drawOn($p);
                })
                ->toString();

            self::assertStringContainsString('/Import1 Do', Pdf::contentText($pdf));
        } finally {
            @unlink($upper);
        }

        // The query string must not reach the extension sniff.
        $method = new \ReflectionMethod(Panel::class, 'sourceExtension');
        self::assertSame('pdf', $method->invoke(Panel::at(0, 0, 1, 1)->showing('https://h/x/plan.PDF?v=2')));
        self::assertSame('png', $method->invoke(Panel::at(0, 0, 1, 1)->showing('https://h/logo.png#frag')));
    }

    public function test_showing_dispatches_a_data_uri_image_to_place_image(): void
    {
        $bytes = (string) file_get_contents(dirname(__DIR__) . '/fixtures/dot-rgba.png');
        $dataUri = 'data:image/png;base64,' . base64_encode($bytes);

        $pdf = Document::create()->using(Pdf::deterministicRenderer())
            ->page(function (PageBuilder $p) use ($dataUri): void {
                $p->size(PageSize::a4())->units(Unit::Pt)->margin(0);
                Panel::at(100, 100, 200, 200)->showing($dataUri)->drawOn($p);
            })
            ->toString();

        self::assertSame(1, substr_count(Pdf::contentText($pdf), '/I1 Do'));
    }

    public function test_showing_dispatches_an_image_source_to_place_image(): void
    {
        $image = dirname(__DIR__) . '/fixtures/bar.jpg';

        $pdf = Document::create()->using(Pdf::deterministicRenderer())
            ->page(function (PageBuilder $p) use ($image): void {
                $p->size(PageSize::a4())->units(Unit::Pt)->margin(0);
                Panel::at(100, 100, 200, 120)->showing($image)->drawOn($p);
            })
            ->toString();

        $content = Pdf::contentText($pdf);
        // bar.jpg is 24x12 (2:1); contain-fitted into the 194x114 inset.
        self::assertSame(1, substr_count($content, '/I1 Do'));
        self::assertMatchesRegularExpression('/194\.00 0 0 97\.00 /', $content);
    }

    public function test_containing_lays_out_blocks_inside_the_frame(): void
    {
        $pdf = Document::create()->using(Pdf::deterministicRenderer())
            ->page(function (PageBuilder $p): void {
                $p->size(PageSize::a4())->units(Unit::Pt)->margin(0);
                Panel::at(50, 50, 300, 200)
                    ->inset(6)
                    ->containing([new Paragraph('Panelled text.')])
                    ->drawOn($p);
            })
            ->toString();

        $content = Pdf::contentText($pdf);
        self::assertStringContainsString('(Panelled text.) Tj', $content);
        // The frame is still drawn around the blocks.
        self::assertMatchesRegularExpression('/50\.00 [\d.]+ 300\.00 0\.75 re/', $content);
    }

    public function test_unknown_source_extension_is_rejected(): void
    {
        $this->expectException(\LogicException::class);

        Document::create()->using(Pdf::deterministicRenderer())
            ->page(function (PageBuilder $p): void {
                $p->size(PageSize::a4());
                Panel::at(0, 0, 10, 10)->showing('notes.txt')->drawOn($p);
            })
            ->toString();
    }

    public function test_panel_in_pairs_with_grid_regions(): void
    {
        $pdf = Document::create()->using(Pdf::deterministicRenderer())
            ->page(function (PageBuilder $p): void {
                $p->size(PageSize::letter())->landscape()->units(Unit::Pt)->margin(18);
                [$left, $right] = $p->grid(gutterPt: 12)->columns(2, 1);
                Panel::in($left->rect())->containing([new Paragraph('left')])->drawOn($p);
                Panel::in($right->rect())->containing([new Paragraph('right')])->drawOn($p);
            })
            ->toString();

        $content = Pdf::contentText($pdf);
        self::assertStringContainsString('(left) Tj', $content);
        self::assertStringContainsString('(right) Tj', $content);
    }
}
