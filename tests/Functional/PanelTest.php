<?php

declare(strict_types=1);

namespace Pdf\Tests\Functional;

use Pdf\Builder\Panel;
use Pdf\Builder\PageBuilder;
use Pdf\Document;
use Pdf\Geometry\BoxAlign;
use Pdf\Geometry\Fit;
use Pdf\Geometry\PageSize;
use Pdf\Geometry\Unit;
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
        self::assertNotSame($base, $base->inset(5));
        self::assertNotSame($base, $base->fitted(Fit::Cover, BoxAlign::TopLeft));
        self::assertNotSame($base, $base->framed(Border::uniform(1.0)));
    }
}
