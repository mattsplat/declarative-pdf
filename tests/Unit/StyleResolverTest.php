<?php

declare(strict_types=1);

namespace Pdf\Tests\Unit;

use Pdf\Node\Heading;
use Pdf\Node\Paragraph;
use Pdf\Style\Style;
use Pdf\Style\StylePatch;
use Pdf\Style\StyleResolver;
use Pdf\Style\Stylesheet;
use PHPUnit\Framework\TestCase;

final class StyleResolverTest extends TestCase
{
    public function test_heading_sizes_scale_off_the_resolved_base_size_not_a_hard_coded_12pt(): void
    {
        $resolver = new StyleResolver();
        $base = Style::default()->resetBlockProperties();
        $base16 = new Style(
            $base->fontFamily,
            $base->fontFace,
            16.0,
            $base->color,
            $base->align,
            $base->lineHeight,
            0.0,
            0.0,
        );

        $h1 = $resolver->resolveBlock(new Heading(1, 'x'), $base16);
        $h4 = $resolver->resolveBlock(new Heading(4, 'x'), $base16);

        self::assertSame(32.0, $h1->fontSizePt, 'h1 is 2x the 16pt base');
        self::assertSame(16.0, $h4->fontSizePt, 'h4 (scale 1.0) matches the base');
        self::assertEqualsWithDelta(32.0 * 0.6, $h1->spaceBeforePt, 1e-9);
    }

    public function test_twelve_point_base_is_unchanged(): void
    {
        $resolver = new StyleResolver();
        $h2 = $resolver->resolveBlock(new Heading(2, 'x'), Style::default());

        self::assertSame(18.0, $h2->fontSizePt); // 12 * 1.5
    }

    public function test_paragraph_default_spacing_still_applies(): void
    {
        $p = (new StyleResolver())->resolveBlock(new Paragraph('x'), Style::default());

        self::assertSame(6.0, $p->spaceAfterPt);
    }

    public function test_font_scale_shrinks_a_hard_coded_block_size(): void
    {
        $p = (new StyleResolver())->withFontScale(0.5)->resolveBlock(
            new Paragraph('x', new StylePatch(fontSizePt: 10.0)),
            Style::default(),
        );

        self::assertSame(5.0, $p->fontSizePt);
    }

    public function test_font_scale_shrinks_a_hard_coded_inline_size(): void
    {
        $inline = (new StyleResolver())->withFontScale(0.5)->resolveInline(
            new StylePatch(fontSizePt: 9.0),
            Style::default(),
        );

        self::assertSame(4.5, $inline->fontSizePt);
    }

    public function test_font_scale_does_not_double_apply_to_an_inherited_size(): void
    {
        // The caller pre-scales the parent (as the Paginator does); a block with
        // no size of its own must simply inherit it, not scale it again.
        $parent = (new StylePatch(fontSizePt: 6.0))->applyTo(Style::default());
        $p = (new StyleResolver())->withFontScale(0.5)->resolveBlock(new Paragraph('x'), $parent);

        self::assertSame(6.0, $p->fontSizePt);
    }

    public function test_without_a_font_scale_hard_coded_sizes_are_untouched(): void
    {
        $p = (new StyleResolver())->resolveBlock(
            new Paragraph('x', new StylePatch(fontSizePt: 10.0)),
            Style::default(),
        );

        self::assertSame(10.0, $p->fontSizePt);
    }

    public function test_font_scale_compounds_across_clones(): void
    {
        $resolver = (new StyleResolver())->withFontScale(0.5)->withFontScale(0.5);
        $p = $resolver->resolveInline(new StylePatch(fontSizePt: 8.0), Style::default());

        self::assertSame(2.0, $p->fontSizePt);
    }

    public function test_a_node_picks_up_a_matching_class_rule(): void
    {
        $sheet = (new Stylesheet())->class('lead', new StylePatch(fontSizePt: 14.0));

        $p = (new StyleResolver($sheet))->resolveBlock(
            new Paragraph('x', new StylePatch(class: 'lead')),
            Style::default(),
        );

        self::assertSame(14.0, $p->fontSizePt);
    }

    public function test_a_class_rule_beats_the_type_rule(): void
    {
        $sheet = (new Stylesheet())
            ->paragraph(new StylePatch(fontSizePt: 10.0))
            ->class('lead', new StylePatch(fontSizePt: 14.0));

        $p = (new StyleResolver($sheet))->resolveBlock(
            new Paragraph('x', new StylePatch(class: 'lead')),
            Style::default(),
        );

        self::assertSame(14.0, $p->fontSizePt);
    }

    public function test_the_nodes_own_patch_beats_the_class_rule(): void
    {
        $sheet = (new Stylesheet())->class('lead', new StylePatch(fontSizePt: 14.0));

        $p = (new StyleResolver($sheet))->resolveBlock(
            new Paragraph('x', new StylePatch(fontSizePt: 20.0, class: 'lead')),
            Style::default(),
        );

        self::assertSame(20.0, $p->fontSizePt);
    }

    public function test_with_two_classes_the_one_listed_later_wins(): void
    {
        $sheet = (new Stylesheet())
            ->class('lead', new StylePatch(fontSizePt: 14.0))
            ->class('callout', new StylePatch(fontSizePt: 18.0));

        $p = (new StyleResolver($sheet))->resolveBlock(
            new Paragraph('x', new StylePatch(class: 'callout lead')),
            Style::default(),
        );

        self::assertSame(14.0, $p->fontSizePt, 'the class listed last in the attribute wins');
    }

    public function test_a_class_with_no_matching_rule_is_a_no_op(): void
    {
        $resolver = new StyleResolver(new Stylesheet());

        $tagged = $resolver->resolveBlock(new Paragraph('x', new StylePatch(class: 'ghost')), Style::default());
        $plain = $resolver->resolveBlock(new Paragraph('x'), Style::default());

        self::assertEquals($plain, $tagged);
    }
}
