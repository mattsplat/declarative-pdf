<?php

declare(strict_types=1);

namespace Pdf\Tests\Unit;

use Pdf\Color\Color;
use Pdf\Node\Callout;
use Pdf\Node\Paragraph;
use Pdf\Style\Edge;
use PHPUnit\Framework\TestCase;

final class CalloutTest extends TestCase
{
    /** @return list<\Pdf\Node\BlockNode> */
    private static function body(Callout $callout): array
    {
        return iterator_to_array($callout->body(), false);
    }

    public function test_a_string_body_becomes_a_single_paragraph(): void
    {
        $nodes = self::body(new Callout('just text'));

        self::assertCount(1, $nodes);
        self::assertInstanceOf(Paragraph::class, $nodes[0]);
    }

    public function test_a_title_is_prepended_as_a_paragraph(): void
    {
        $nodes = self::body(new Callout([new Paragraph('a'), new Paragraph('b')], title: 'Heads up'));

        self::assertCount(3, $nodes);
        self::assertInstanceOf(Paragraph::class, $nodes[0]);
    }

    public function test_patch_puts_the_accent_on_the_chosen_edge_only(): void
    {
        $patch = (new Callout('x', accent: Color::rgb(10, 20, 30), accentEdge: Edge::Left, accentWidthPt: 4.0))->patch();

        self::assertNotNull($patch->border);
        self::assertSame(4.0, $patch->border->widthPt->left);
        self::assertSame(0.0, $patch->border->widthPt->right);
        self::assertSame(0.0, $patch->border->widthPt->top);
        self::assertEquals(Color::rgb(10, 20, 30), $patch->border->color);
    }

    public function test_the_tint_is_the_background(): void
    {
        $patch = (new Callout('x', tint: Color::rgb(250, 250, 200)))->patch();

        self::assertEquals(Color::rgb(250, 250, 200), $patch->background);
    }

    public function test_an_empty_block_body_is_rejected(): void
    {
        $this->expectException(\Pdf\Exception\PdfException::class);

        new Callout([]);
    }
}
