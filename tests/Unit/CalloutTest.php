<?php

declare(strict_types=1);

namespace Pdf\Tests\Unit;

use Pdf\Color\Color;
use Pdf\Geometry\Edge;
use Pdf\Node\Callout;
use Pdf\Node\Paragraph;
use Pdf\Text\InlineSequence;
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

    public function test_an_inline_sequence_title_is_carried_onto_the_title_paragraph(): void
    {
        $title = InlineSequence::of('Bold ')->withItalic('note');
        $nodes = self::body(new Callout('body', title: $title));

        self::assertInstanceOf(Paragraph::class, $nodes[0]);
        self::assertSame($title, $nodes[0]->content);
    }

    public function test_the_accent_sits_on_the_chosen_edge_only_for_every_edge(): void
    {
        foreach (Edge::cases() as $edge) {
            $widths = (new Callout('x', accentEdge: $edge, accentWidthPt: 4.0))->patch()->border?->widthPt;
            self::assertNotNull($widths);

            $expected = match ($edge) {
                Edge::Top => [4.0, 0.0, 0.0, 0.0],
                Edge::Right => [0.0, 4.0, 0.0, 0.0],
                Edge::Bottom => [0.0, 0.0, 4.0, 0.0],
                Edge::Left => [0.0, 0.0, 0.0, 4.0],
            };
            self::assertSame($expected, [$widths->top, $widths->right, $widths->bottom, $widths->left]);
        }
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
