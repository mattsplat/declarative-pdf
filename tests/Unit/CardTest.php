<?php

declare(strict_types=1);

namespace Pdf\Tests\Unit;

use Pdf\Color\Color;
use Pdf\Node\Card;
use Pdf\Node\Paragraph;
use Pdf\Node\Rule;
use Pdf\Style\Border;
use PHPUnit\Framework\TestCase;

final class CardTest extends TestCase
{
    /** @return list<\Pdf\Node\BlockNode> */
    private static function body(Card $card): array
    {
        return iterator_to_array($card->body(), false);
    }

    public function test_a_titled_card_yields_title_rule_then_content(): void
    {
        $nodes = self::body(new Card([new Paragraph('body')], title: 'Heading'));

        self::assertCount(3, $nodes);
        self::assertInstanceOf(Paragraph::class, $nodes[0]);
        self::assertInstanceOf(Rule::class, $nodes[1]);
        self::assertInstanceOf(Paragraph::class, $nodes[2]);
    }

    public function test_the_rule_can_be_turned_off_and_a_titleless_card_is_just_its_content(): void
    {
        self::assertCount(2, self::body(new Card([new Paragraph('b')], title: 'H', rule: false)));
        self::assertCount(1, self::body(new Card([new Paragraph('b')])));
    }

    public function test_patch_carries_padding_border_and_background(): void
    {
        $patch = (new Card([new Paragraph('b')], background: Color::rgb(240, 240, 240)))->patch();

        self::assertNotNull($patch->paddingPt);
        self::assertInstanceOf(Border::class, $patch->border);
        self::assertTrue($patch->border->isVisible());
        self::assertEquals(Color::rgb(240, 240, 240), $patch->background);
    }

    public function test_border_none_disables_the_frame(): void
    {
        $patch = (new Card([new Paragraph('b')], border: Border::none()))->patch();

        self::assertInstanceOf(Border::class, $patch->border);
        self::assertFalse($patch->border->isVisible());
    }

    public function test_an_empty_card_is_rejected(): void
    {
        $this->expectException(\Pdf\Exception\PdfException::class);

        new Card([]);
    }
}
