<?php

declare(strict_types=1);

namespace Pdf\Tests\Unit;

use Pdf\Builder\PageBuilder;
use Pdf\Document;
use Pdf\Exception\LayoutException;
use Pdf\Font\FontRepository;
use Pdf\Font\FontFace;
use Pdf\Geometry\Unit;
use Pdf\Node\Paragraph;
use Pdf\Render\DocumentRenderer;
use Pdf\Style\StylePatch;
use Pdf\Tests\Support\Pdf;
use Pdf\Text\Encoding;
use PHPUnit\Framework\TestCase;

final class PageBuilderMeasurementTest extends TestCase
{
    /**
     * Run a page configurator through the builder (which injects a measurer)
     * and hand back whatever it computed.
     *
     * @param callable(PageBuilder): mixed $configure
     */
    private function measure(callable $configure, ?DocumentRenderer $renderer = null): float
    {
        $captured = null;
        Document::create()
            ->using($renderer ?? Pdf::deterministicRenderer())
            ->page(function (PageBuilder $p) use ($configure, &$captured): void {
                $captured = $configure($p);
                $p->paragraph('keeps the page non-empty');
            })
            ->build();

        self::assertIsFloat($captured);

        return $captured;
    }

    public function test_text_width_is_reported_in_the_pages_units(): void
    {
        $pt = $this->measure(fn (PageBuilder $p) => $p->units(Unit::Pt)->textWidth('DETAIL'));
        $mm = $this->measure(fn (PageBuilder $p) => $p->units(Unit::Mm)->textWidth('DETAIL'));

        // Helvetica 12pt: 3501/1000em * 12
        self::assertEqualsWithDelta(42.012, $pt, 1e-9);
        self::assertEqualsWithDelta($pt * 25.4 / 72.0, $mm, 1e-9);
    }

    public function test_text_width_honours_an_inline_patch(): void
    {
        $regular = $this->measure(fn (PageBuilder $p) => $p->units(Unit::Pt)->textWidth('DETAIL'));
        $bold = $this->measure(
            fn (PageBuilder $p) => $p->units(Unit::Pt)->textWidth('DETAIL', new StylePatch(bold: true)),
        );

        self::assertEqualsWithDelta(42.012, $regular, 1e-9);
        self::assertEqualsWithDelta(43.332, $bold, 1e-9);
    }

    public function test_measure_blocks_returns_the_natural_stacked_height(): void
    {
        $oneLine = $this->measure(
            fn (PageBuilder $p) => $p->units(Unit::Pt)->measureBlocks([new Paragraph('one line')], 400.0),
        );
        $wrapped = $this->measure(
            fn (PageBuilder $p) => $p->units(Unit::Pt)->measureBlocks(
                [new Paragraph(str_repeat('word ', 60))],
                120.0,
            ),
        );

        // Base 12pt * lineHeight 1.15.
        self::assertEqualsWithDelta(13.8, $oneLine, 1e-9);
        self::assertGreaterThan($oneLine * 5, $wrapped);
        self::assertEqualsWithDelta(0.0, fmod($wrapped, 13.8), 1e-6);
    }

    public function test_measure_blocks_converts_width_in_and_height_out_through_the_unit(): void
    {
        $mm = $this->measure(
            fn (PageBuilder $p) => $p->units(Unit::Mm)->measureBlocks([new Paragraph('one line')], 140.0),
        );

        self::assertEqualsWithDelta(13.8 * 25.4 / 72.0, $mm, 1e-9);
    }

    public function test_a_registered_font_is_used_for_measurement(): void
    {
        $fonts = FontRepository::withBundledFonts();
        $fonts->register(
            'CevicheOne',
            FontFace::regular(),
            dirname(__DIR__) . '/fixtures/CevicheOne-Regular.json',
        );
        $renderer = new DocumentRenderer($fonts);

        $measured = $this->measure(
            fn (PageBuilder $p) => $p->units(Unit::Pt)->textWidth(
                'Enjoy',
                new StylePatch(fontFamily: 'CevicheOne', fontSizePt: 40.0),
            ),
            $renderer,
        );

        $definition = $fonts->resolve('CevicheOne', FontFace::regular());
        $expected = $definition->metrics()->stringWidth(
            Encoding::forFont('Enjoy', $definition->encoding),
            40.0,
        );

        self::assertEqualsWithDelta($expected, $measured, 1e-9);
        self::assertNotEqualsWithDelta(
            FontRepository::withBundledFonts()
                ->resolve('Helvetica', FontFace::regular())
                ->metrics()
                ->stringWidth('Enjoy', 40.0),
            $measured,
            1e-6,
        );
    }

    public function test_helpers_throw_when_the_builder_has_no_renderer(): void
    {
        $this->expectException(LayoutException::class);

        (new PageBuilder())->textWidth('x');
    }
}
