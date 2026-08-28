<?php

declare(strict_types=1);

namespace Pdf\Tests\Unit;

use Pdf\Node\Heading;
use Pdf\Node\Paragraph;
use Pdf\Style\Style;
use Pdf\Style\StyleResolver;
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
}
