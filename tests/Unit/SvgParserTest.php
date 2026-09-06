<?php

declare(strict_types=1);

namespace Pdf\Tests\Unit;

use Pdf\Color\Color;
use Pdf\Exception\SvgException;
use Pdf\Geometry\PathCommand;
use Pdf\Style\FillRule;
use Pdf\Style\LineCap;
use Pdf\Style\LinearGradient;
use Pdf\Style\RadialGradient;
use Pdf\Svg\SvgDocument;
use Pdf\Svg\SvgParser;
use PHPUnit\Framework\TestCase;

final class SvgParserTest extends TestCase
{
    private function parse(string $body, string $attributes = 'viewBox="0 0 24 24"'): SvgDocument
    {
        return (new SvgParser())->parse(
            '<svg xmlns="http://www.w3.org/2000/svg" ' . $attributes . '>' . $body . '</svg>',
        );
    }

    /** Every operand of a shape's geometry, for terse coordinate assertions. */
    private function coordinates(SvgDocument $document, int $shape = 0): string
    {
        $parts = [];
        foreach ($document->shapes[$shape]->commands as $command) {
            foreach ($command->points as $point) {
                $parts[] = sprintf('%.2F,%.2F', $point->x, $point->y);
            }
        }

        return implode(' ', $parts);
    }

    public function test_a_viewbox_alone_sizes_the_document_in_points(): void
    {
        // 24 user units are 24 CSS pixels, and a pixel is three quarters of a point.
        $document = $this->parse('<rect width="24" height="24"/>');

        self::assertSame(18.0, $document->widthPt);
        self::assertSame(18.0, $document->heightPt);
        self::assertSame('0.00,0.00 18.00,0.00 18.00,18.00 0.00,18.00', $this->coordinates($document));
    }

    public function test_an_explicit_size_scales_the_viewbox_to_match(): void
    {
        $document = $this->parse('<rect width="24" height="24"/>', 'viewBox="0 0 24 24" width="48" height="48"');

        self::assertSame(36.0, $document->widthPt);
        self::assertSame('0.00,0.00 36.00,0.00 36.00,36.00 0.00,36.00', $this->coordinates($document));
    }

    public function test_physical_units_on_the_root_convert_to_points(): void
    {
        $document = $this->parse('<rect width="10" height="10"/>', 'viewBox="0 0 10 10" width="25.4mm" height="25.4mm"');

        self::assertSame(72.0, round($document->widthPt, 6));
    }

    public function test_a_viewbox_origin_offsets_the_geometry(): void
    {
        $document = $this->parse('<rect x="10" y="10" width="10" height="10"/>', 'viewBox="10 10 10 10"');

        self::assertSame('0.00,0.00 7.50,0.00 7.50,7.50 0.00,7.50', $this->coordinates($document));
    }

    public function test_without_a_viewbox_user_units_are_pixels(): void
    {
        $document = $this->parse('<rect width="8" height="8"/>', 'width="8" height="8"');

        self::assertSame(6.0, $document->widthPt);
        self::assertSame('0.00,0.00 6.00,0.00 6.00,6.00 0.00,6.00', $this->coordinates($document));
    }

    public function test_preserve_aspect_ratio_letterboxes_a_mismatched_viewbox(): void
    {
        // A 10x10 viewBox in a 20x10pt viewport scales by 1 and centres on x.
        $document = $this->parse(
            '<rect width="10" height="10"/>',
            'viewBox="0 0 10 10" width="26.666667" height="13.333333"',
        );

        self::assertSame(20.0, round($document->widthPt, 4));
        self::assertSame('5.00,0.00 15.00,0.00 15.00,10.00 5.00,10.00', $this->coordinates($document));
    }

    public function test_preserve_aspect_ratio_none_stretches_each_axis(): void
    {
        $document = $this->parse(
            '<rect width="10" height="10"/>',
            'viewBox="0 0 10 10" width="26.666667" height="13.333333" preserveAspectRatio="none"',
        );

        self::assertSame('0.00,0.00 20.00,0.00 20.00,10.00 0.00,10.00', $this->coordinates($document));
    }

    public function test_shape_elements_all_become_paths(): void
    {
        $document = $this->parse(
            '<rect width="4" height="4"/><circle cx="4" cy="4" r="2"/><ellipse cx="4" cy="4" rx="2" ry="1"/>'
            . '<line x1="0" y1="0" x2="4" y2="4" stroke="black"/>'
            . '<polyline points="0,0 4,4" stroke="black"/><polygon points="0,0 4,0 4,4"/>'
            . '<path d="M0 0 L4 4"/>',
        );

        self::assertCount(7, $document->shapes);
    }

    public function test_a_rounded_rectangle_uses_corner_curves(): void
    {
        $document = $this->parse('<rect width="24" height="24" rx="4"/>');
        $ops = array_map(static fn (PathCommand $c): string => $c->op->name, $document->shapes[0]->commands);

        self::assertSame(
            ['MoveTo', 'LineTo', 'CurveTo', 'LineTo', 'CurveTo', 'LineTo', 'CurveTo', 'LineTo', 'CurveTo', 'Close'],
            $ops,
        );
    }

    public function test_an_omitted_ry_mirrors_rx(): void
    {
        self::assertEquals(
            $this->parse('<rect width="24" height="24" rx="4" ry="4"/>')->shapes,
            $this->parse('<rect width="24" height="24" rx="4"/>')->shapes,
        );
    }

    public function test_a_transform_is_baked_into_the_coordinates(): void
    {
        $document = $this->parse('<rect width="8" height="8" transform="translate(8,8)"/>');

        self::assertSame('6.00,6.00 12.00,6.00 12.00,12.00 6.00,12.00', $this->coordinates($document));
    }

    public function test_nested_group_transforms_compose(): void
    {
        $document = $this->parse(
            '<g transform="translate(8,0)"><g transform="scale(2)"><rect width="4" height="4"/></g></g>',
        );

        // scale first, then translate: x runs 8..16 user units, 6..12 points.
        self::assertSame('6.00,0.00 12.00,0.00 12.00,6.00 6.00,6.00', $this->coordinates($document));
    }

    public function test_presentation_attributes_are_inherited_through_groups(): void
    {
        $document = $this->parse('<g fill="#ff0000" fill-rule="evenodd"><rect width="4" height="4"/></g>');
        $paint = $document->shapes[0]->paint;

        self::assertTrue(Color::rgb(255, 0, 0)->equals($paint->fill instanceof Color ? $paint->fill : Color::black()));
        self::assertSame(FillRule::EvenOdd, $paint->fillRule);
    }

    public function test_an_inline_style_attribute_overrides_a_presentation_attribute(): void
    {
        $document = $this->parse('<rect width="4" height="4" fill="#ff0000" style="fill:#00ff00;stroke-linecap:round"/>');
        $paint = $document->shapes[0]->paint;

        self::assertTrue(Color::rgb(0, 255, 0)->equals($paint->fill instanceof Color ? $paint->fill : Color::black()));
        self::assertSame(LineCap::Round, $paint->lineCap);
    }

    public function test_stroke_width_scales_with_the_transform(): void
    {
        // 2 user units at scale 2, then 0.75 points per user unit.
        $document = $this->parse('<g transform="scale(2)"><rect width="4" height="4" stroke="black" stroke-width="2"/></g>');

        self::assertSame(3.0, $document->shapes[0]->paint->strokeWidthPt);
    }

    public function test_an_unpainted_shape_is_dropped(): void
    {
        self::assertSame([], $this->parse('<rect width="4" height="4" fill="none"/>')->shapes);
        self::assertSame([], $this->parse('<rect width="4" height="4" display="none"/>')->shapes);
        self::assertSame([], $this->parse('<g visibility="hidden"><rect width="4" height="4"/></g>')->shapes);
    }

    public function test_group_opacity_survives_a_child_setting_its_own_fill_opacity(): void
    {
        // `opacity` composites the group; it is not a property the child's own
        // fill-opacity can override.
        $document = $this->parse('<g opacity="0.5"><rect width="4" height="4" fill-opacity="1"/></g>');

        self::assertSame(0.5, round($document->shapes[0]->paint->fillAlpha, 6));
    }

    public function test_nested_group_opacities_multiply(): void
    {
        $document = $this->parse('<g opacity="0.5"><g opacity="0.5"><rect width="4" height="4"/></g></g>');

        self::assertSame(0.25, round($document->shapes[0]->paint->fillAlpha, 6));
    }

    public function test_the_three_sources_of_transparency_compose(): void
    {
        // colour alpha 0.5, fill-opacity 0.5, group opacity 0.5.
        $document = $this->parse(
            '<g opacity="0.5"><rect width="4" height="4" fill="#ff000080" fill-opacity="0.5"/></g>',
        );

        self::assertSame(0.125, round($document->shapes[0]->paint->fillAlpha, 3));
    }

    public function test_a_later_fill_replaces_an_earlier_colour_alpha(): void
    {
        // The inline style wins outright, so the attribute's alpha is gone —
        // not multiplied into the result.
        $document = $this->parse('<rect width="4" height="4" fill="#ff000080" style="fill:#00ff00"/>');

        self::assertSame(1.0, round($document->shapes[0]->paint->fillAlpha, 6));
    }

    public function test_an_unsupported_feature_on_a_hidden_element_is_not_refused(): void
    {
        // Nothing it declares gets drawn, so a filter we cannot reproduce
        // cannot mislead anyone. Inkscape files are full of hidden layers.
        $document = $this->parse(
            '<g display="none" filter="url(#f)"><rect width="4" height="4"/></g>'
            . '<rect width="8" height="8"/>',
        );

        self::assertCount(1, $document->shapes);
    }

    public function test_use_instantiates_a_referenced_element(): void
    {
        $document = $this->parse(
            '<defs><rect id="box" width="4" height="4"/></defs><use href="#box" x="8" y="8"/>',
        );

        self::assertCount(1, $document->shapes);
        self::assertSame('6.00,6.00 9.00,6.00 9.00,9.00 6.00,9.00', $this->coordinates($document));
    }

    public function test_use_supports_the_xlink_spelling(): void
    {
        $document = (new SvgParser())->parse(
            '<svg xmlns="http://www.w3.org/2000/svg" xmlns:xlink="http://www.w3.org/1999/xlink" viewBox="0 0 24 24">'
            . '<defs><rect id="box" width="4" height="4"/></defs><use xlink:href="#box"/></svg>',
        );

        self::assertCount(1, $document->shapes);
    }

    public function test_a_symbol_viewbox_maps_onto_the_size_the_use_asks_for(): void
    {
        // The classic icon sprite: a 0..10 symbol drawn into a 20x20 slot.
        $document = $this->parse(
            '<defs><symbol id="icon" viewBox="0 0 10 10"><rect width="10" height="10"/></symbol></defs>'
            . '<use href="#icon" width="20" height="20"/>',
            'viewBox="0 0 20 20"',
        );

        self::assertSame('0.00,0.00 15.00,0.00 15.00,15.00 0.00,15.00', $this->coordinates($document));
    }

    public function test_a_use_cycle_is_rejected_rather_than_recursing(): void
    {
        $this->expectException(SvgException::class);
        $this->parse('<g id="loop"><use href="#loop"/></g>');
    }

    public function test_a_linear_gradient_fill_resolves_to_a_shading(): void
    {
        $document = $this->parse(
            '<defs><linearGradient id="g"><stop offset="0" stop-color="#ff0000"/>'
            . '<stop offset="1" stop-color="#0000ff"/></linearGradient></defs>'
            . '<rect width="24" height="24" fill="url(#g)"/>',
        );

        $fill = $document->shapes[0]->paint->fill;
        self::assertInstanceOf(LinearGradient::class, $fill);
        // objectBoundingBox 0..1 across a full-width rect is 0..1 of the SVG box.
        self::assertSame(0.0, round($fill->x0, 6));
        self::assertSame(1.0, round($fill->x1, 6));
        self::assertCount(2, $fill->stops);
    }

    public function test_a_gradient_inherits_stops_through_href(): void
    {
        $document = $this->parse(
            '<defs><linearGradient id="base"><stop offset="0" stop-color="#000"/>'
            . '<stop offset="1" stop-color="#fff"/></linearGradient>'
            . '<linearGradient id="g" href="#base" x1="0" y1="0" x2="0" y2="1"/></defs>'
            . '<rect width="24" height="24" fill="url(#g)"/>',
        );

        $fill = $document->shapes[0]->paint->fill;
        self::assertInstanceOf(LinearGradient::class, $fill);
        self::assertCount(2, $fill->stops);
        self::assertSame(1.0, round($fill->y1, 6));
    }

    public function test_a_radial_radius_is_a_length_not_a_coordinate(): void
    {
        // The shape sits away from the origin, so a radius that wrongly picked
        // up the bounding box's offset would come out several times too large.
        $document = $this->parse(
            '<defs><radialGradient id="g"><stop offset="0" stop-color="#fff"/>'
            . '<stop offset="1" stop-color="#000"/></radialGradient></defs>'
            . '<circle cx="80" cy="80" r="10" fill="url(#g)"/>',
            'viewBox="0 0 96 96"',
        );

        $fill = $document->shapes[0]->paint->fill;
        self::assertInstanceOf(RadialGradient::class, $fill);
        // r=50% of a 20-unit box is 10 units, of a 96-unit box side.
        self::assertSame(0.1042, round($fill->radius, 4));
    }

    public function test_a_gradient_transform_acts_in_the_bounding_box_space(): void
    {
        // translate(0.5,0) in objectBoundingBox units is half the shape's own
        // width, not half a user unit.
        $document = $this->parse(
            '<defs><linearGradient id="g" gradientTransform="translate(0.5,0)">'
            . '<stop offset="0" stop-color="#fff"/><stop offset="1" stop-color="#000"/>'
            . '</linearGradient></defs><rect width="20" height="20" fill="url(#g)"/>',
            'viewBox="0 0 96 96"',
        );

        $fill = $document->shapes[0]->paint->fill;
        self::assertInstanceOf(LinearGradient::class, $fill);
        self::assertSame(0.1042, round($fill->x0, 4));
    }

    public function test_a_gradient_with_no_stops_paints_nothing(): void
    {
        // Paint would otherwise fall back to a hairline black outline the SVG
        // never asked for.
        $document = $this->parse(
            '<defs><linearGradient id="empty"/></defs><rect width="20" height="20" fill="url(#empty)"/>',
        );

        self::assertSame([], $document->shapes);
    }

    public function test_transform_none_is_the_identity(): void
    {
        self::assertEquals(
            $this->parse('<rect width="4" height="4"/>')->shapes,
            $this->parse('<rect width="4" height="4" transform="none"/>')->shapes,
        );
    }

    public function test_preserve_aspect_ratio_tolerates_a_leading_defer(): void
    {
        // `defer` shifts the alignment and meet/slice tokens along by one.
        $document = $this->parse(
            '<rect width="10" height="10"/>',
            'viewBox="0 0 10 10" width="53.333333" height="26.666667" preserveAspectRatio="defer xMidYMid slice"',
        );

        // slice scales to cover (x4), so the square overflows the 20pt height
        // and spans the full 40pt width; meet would have scaled x2 and inset it.
        self::assertSame(40.0, round($document->shapes[0]->commands[1]->points[0]->x, 4));
    }

    public function test_a_radial_gradient_resolves_to_a_radial_shading(): void
    {
        $document = $this->parse(
            '<defs><radialGradient id="g"><stop offset="0" stop-color="#fff"/>'
            . '<stop offset="1" stop-color="#000"/></radialGradient></defs>'
            . '<rect width="24" height="24" fill="url(#g)"/>',
        );

        self::assertInstanceOf(RadialGradient::class, $document->shapes[0]->paint->fill);
    }

    public function test_a_single_stop_gradient_collapses_to_a_solid_colour(): void
    {
        $document = $this->parse(
            '<defs><linearGradient id="g"><stop offset="0" stop-color="#ff0000"/></linearGradient></defs>'
            . '<rect width="24" height="24" fill="url(#g)"/>',
        );

        self::assertInstanceOf(Color::class, $document->shapes[0]->paint->fill);
    }

    public function test_non_rendering_elements_are_skipped_silently(): void
    {
        $document = (new SvgParser())->parse(
            '<svg xmlns="http://www.w3.org/2000/svg" xmlns:inkscape="http://www.inkscape.org/namespaces/inkscape"'
            . ' viewBox="0 0 24 24">'
            . '<title>An icon</title><desc>Drawn by hand</desc><metadata><rdf/></metadata>'
            . '<!-- a comment --><inkscape:grid id="grid"/>'
            . '<rect width="24" height="24"/></svg>',
        );

        self::assertCount(1, $document->shapes);
    }

    /**
     * @return list<array{0: string, 1: string}>
     */
    public static function refusedMarkup(): array
    {
        return [
            ['text', '<text x="0" y="10">hello</text>'],
            ['filter', '<filter id="f"/><rect width="4" height="4" filter="url(#f)"/>'],
            ['mask', '<mask id="m"/>'],
            ['clipPath', '<clipPath id="c"/>'],
            ['pattern', '<pattern id="p"/>'],
            ['stylesheet', '<style>rect { fill: red }</style>'],
            ['nested svg', '<svg viewBox="0 0 4 4"/>'],
            ['embedded image', '<image href="x.png" width="4" height="4"/>'],
            ['unknown element', '<sparkle width="4"/>'],
            ['dashed stroke', '<rect width="4" height="4" stroke="black" stroke-dasharray="2 2"/>'],
            ['gradient stroke', '<defs><linearGradient id="g"><stop offset="0" stop-color="#000"/>'
                . '<stop offset="1" stop-color="#fff"/></linearGradient></defs>'
                . '<rect width="4" height="4" stroke="url(#g)"/>'],
            ['per-stop opacity', '<defs><linearGradient id="g"><stop offset="0" stop-color="#000" stop-opacity="0.5"/>'
                . '<stop offset="1" stop-color="#fff"/></linearGradient></defs>'
                . '<rect width="4" height="4" fill="url(#g)"/>'],
        ];
    }

    /**
     * A feature we cannot draw must fail loudly rather than render a document
     * that is silently missing part of the picture.
     */
    #[\PHPUnit\Framework\Attributes\DataProvider('refusedMarkup')]
    public function test_unsupported_features_are_refused(string $feature, string $markup): void
    {
        $this->expectException(SvgException::class);
        $this->parse($markup);
    }

    public function test_malformed_xml_is_rejected(): void
    {
        $this->expectException(SvgException::class);
        (new SvgParser())->parse('<svg><rect></svg>');
    }

    public function test_a_non_svg_root_is_rejected(): void
    {
        $this->expectException(SvgException::class);
        (new SvgParser())->parse('<html><body/></html>');
    }

    public function test_an_unsizeable_document_is_rejected(): void
    {
        $this->expectException(SvgException::class);
        (new SvgParser())->parse('<svg xmlns="http://www.w3.org/2000/svg"><rect width="4" height="4"/></svg>');
    }
}
