<?php

declare(strict_types=1);

namespace Pdf\Tests\Functional;

use Pdf\Document;
use Pdf\Exception\SvgException;
use Pdf\Geometry\Fit;
use Pdf\Node\Svg;
use Pdf\Style\TextAlign;
use Pdf\Tests\Support\Golden;
use Pdf\Tests\Support\Pdf;
use PHPUnit\Framework\TestCase;

final class SvgRenderTest extends TestCase
{
    private function fixture(string $name): string
    {
        return dirname(__DIR__) . '/fixtures/' . $name;
    }

    public function test_an_svg_renders_as_vector_operators_not_an_xobject(): void
    {
        $pdf = Document::create()
            ->using(Pdf::deterministicRenderer())
            ->page(fn ($p) => $p->svg($this->fixture('icon.svg'), width: 20.0))
            ->toString();

        $content = Pdf::contentText($pdf);

        // Curves and strokes on the page itself, and no image resource drawn.
        self::assertStringContainsString(" c\n", $content);
        self::assertStringContainsString("\nS\n", $content);
        self::assertStringNotContainsString('Do', $content);
        self::assertStringNotContainsString('/DCTDecode', $pdf);
    }

    public function test_the_intrinsic_size_comes_from_the_svg_itself(): void
    {
        // icon.svg is 24x24 CSS pixels, so 18x18 points.
        $pdf = Document::create()
            ->using(Pdf::deterministicRenderer())
            ->page(fn ($p) => $p->svg($this->fixture('icon.svg')))
            ->toString();

        // A 2-unit stroke lands at 1.5pt, which is the px-to-point ratio itself.
        self::assertStringContainsString('1.50 w', Pdf::contentText($pdf));
    }

    public function test_a_stated_width_scales_the_whole_drawing(): void
    {
        $narrow = Document::create()
            ->using(Pdf::deterministicRenderer())
            ->page(fn ($p) => $p->svg($this->fixture('icon.svg'), width: 10.0))
            ->toString();
        $wide = Document::create()
            ->using(Pdf::deterministicRenderer())
            ->page(fn ($p) => $p->svg($this->fixture('icon.svg'), width: 40.0))
            ->toString();

        self::assertNotSame(Pdf::contentText($narrow), Pdf::contentText($wide));
    }

    public function test_a_gradient_fill_becomes_a_shading_resource(): void
    {
        $pdf = Document::create()
            ->using(Pdf::deterministicRenderer())
            ->page(fn ($p) => $p->svg($this->fixture('logo.svg'), width: 60.0))
            ->toString();

        self::assertStringContainsString('/Shading <<', $pdf);
        self::assertStringContainsString('/ShadingType 2', $pdf);
        self::assertStringContainsString(' sh', Pdf::contentText($pdf));
    }

    public function test_fill_opacity_emits_an_ext_gstate(): void
    {
        $pdf = Document::create()
            ->using(Pdf::deterministicRenderer())
            ->page(fn ($p) => $p->svg($this->fixture('logo.svg'), width: 60.0))
            ->toString();

        self::assertStringContainsString('/ExtGState <<', $pdf);
        self::assertStringContainsString('/Type /ExtGState /ca 0.850', $pdf);
        self::assertStringContainsString('/GSa850_1000 gs', Pdf::contentText($pdf));
    }

    public function test_transparency_lifts_the_document_to_pdf_1_4(): void
    {
        // /ExtGState transparency is a 1.4 feature; a 1.3 reader is entitled to
        // ignore it and paint the artwork opaque.
        $translucent = Document::create()
            ->using(Pdf::deterministicRenderer())
            ->page(fn ($p) => $p->svg($this->fixture('logo.svg'), width: 60.0))
            ->toString();

        self::assertStringStartsWith('%PDF-1.4', $translucent);
    }

    public function test_an_opaque_svg_stays_pdf_1_3(): void
    {
        $opaque = Document::create()
            ->using(Pdf::deterministicRenderer())
            ->page(fn ($p) => $p->svg($this->fixture('icon.svg'), width: 20.0))
            ->toString();

        self::assertStringStartsWith('%PDF-1.3', $opaque);
    }

    public function test_an_opaque_svg_emits_no_graphics_state_at_all(): void
    {
        $pdf = Document::create()
            ->using(Pdf::deterministicRenderer())
            ->page(fn ($p) => $p->svg($this->fixture('icon.svg'), width: 20.0))
            ->toString();

        self::assertStringNotContainsString('/ExtGState', $pdf);
        self::assertStringNotContainsString(' gs', Pdf::contentText($pdf));
    }

    public function test_image_dispatches_an_svg_to_the_vector_path(): void
    {
        $viaImage = Document::create()
            ->using(Pdf::deterministicRenderer())
            ->page(fn ($p) => $p->image($this->fixture('icon.svg'), width: 20.0))
            ->toString();
        $viaSvg = Document::create()
            ->using(Pdf::deterministicRenderer())
            ->page(fn ($p) => $p->svg($this->fixture('icon.svg'), width: 20.0))
            ->toString();

        self::assertSame($viaSvg, $viaImage);
    }

    public function test_a_placed_svg_is_fitted_into_its_rectangle(): void
    {
        $pdf = Document::create()
            ->using(Pdf::deterministicRenderer())
            ->page(fn ($p) => $p->placeSvg(20.0, 20.0, 60.0, 30.0, $this->fixture('logo.svg'), Fit::Contain))
            ->toString();

        self::assertStringContainsString(" c\n", Pdf::contentText($pdf));
        self::assertStringNotContainsString('Do', Pdf::contentText($pdf));
    }

    public function test_place_image_dispatches_an_svg_too(): void
    {
        $viaImage = Document::create()
            ->using(Pdf::deterministicRenderer())
            ->page(fn ($p) => $p->placeImage(20.0, 20.0, 60.0, 30.0, $this->fixture('logo.svg')))
            ->toString();
        $viaSvg = Document::create()
            ->using(Pdf::deterministicRenderer())
            ->page(fn ($p) => $p->placeSvg(20.0, 20.0, 60.0, 30.0, $this->fixture('logo.svg')))
            ->toString();

        self::assertSame($viaSvg, $viaImage);
    }

    public function test_a_cover_fit_clips_the_placed_svg(): void
    {
        $pdf = Document::create()
            ->using(Pdf::deterministicRenderer())
            ->page(fn ($p) => $p->placeSvg(20.0, 20.0, 40.0, 40.0, $this->fixture('logo.svg'), Fit::Cover))
            ->toString();

        self::assertStringContainsString('re W n', Pdf::contentText($pdf));
    }

    public function test_markup_held_in_memory_renders_without_a_file(): void
    {
        $pdf = Document::create()
            ->using(Pdf::deterministicRenderer())
            ->page(fn ($p) => $p->add(Svg::fromString(
                '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 10 10">'
                . '<rect width="10" height="10" fill="#ff0000"/></svg>',
                width: 20.0,
            )))
            ->toString();

        self::assertStringContainsString('1.000 0.000 0.000 rg', Pdf::contentText($pdf));
    }

    public function test_alignment_shifts_the_drawing_within_the_content_box(): void
    {
        $left = Document::create()
            ->using(Pdf::deterministicRenderer())
            ->page(fn ($p) => $p->svg($this->fixture('icon.svg'), width: 20.0, align: TextAlign::Left))
            ->toString();
        $right = Document::create()
            ->using(Pdf::deterministicRenderer())
            ->page(fn ($p) => $p->svg($this->fixture('icon.svg'), width: 20.0, align: TextAlign::Right))
            ->toString();

        self::assertNotSame(Pdf::contentText($left), Pdf::contentText($right));
    }

    public function test_a_missing_file_is_reported_clearly(): void
    {
        $this->expectException(SvgException::class);
        $this->expectExceptionMessage('SVG file not found');

        Document::create()
            ->using(Pdf::deterministicRenderer())
            ->page(fn ($p) => $p->svg($this->fixture('nope.svg')))
            ->toString();
    }

    public function test_an_unsupported_feature_names_the_file_it_came_from(): void
    {
        $this->expectException(SvgException::class);
        $this->expectExceptionMessage('unsupported.svg');

        Document::create()
            ->using(Pdf::deterministicRenderer())
            ->page(fn ($p) => $p->svg($this->fixture('unsupported.svg')))
            ->toString();
    }

    public function test_svg_output_is_byte_stable(): void
    {
        $pdf = Document::create()
            ->using(Pdf::deterministicRenderer())
            ->page(function ($p): void {
                $p->heading(2, 'Vector artwork');
                $p->svg($this->fixture('icon.svg'), width: 20.0);
                $p->svg($this->fixture('logo.svg'), width: 80.0);
                $p->placeSvg(120.0, 200.0, 60.0, 20.0, $this->fixture('logo.svg'));
            })
            ->toString();

        Golden::assert('svg.pdf', $pdf);
    }
}
