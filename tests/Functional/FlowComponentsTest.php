<?php

declare(strict_types=1);

namespace Pdf\Tests\Functional;

use Pdf\Builder\CoverLayout;
use Pdf\Color\Color;
use Pdf\Document;
use Pdf\Node\Callout;
use Pdf\Node\Card;
use Pdf\Node\DefinitionList;
use Pdf\Node\Paragraph;
use Pdf\Node\Row;
use Pdf\Style\ColumnWidth;
use Pdf\Style\Edge;
use Pdf\Style\StylePatch;
use Pdf\Tests\Support\Golden;
use Pdf\Tests\Support\Pdf;
use PHPUnit\Framework\TestCase;

final class FlowComponentsTest extends TestCase
{
    private function document(): string
    {
        return Document::create()->using(Pdf::deterministicRenderer())
            ->cover(fn ($c) => $c
                ->layout(CoverLayout::BottomBand)
                ->title('Flow components')
                ->subtitle('Card, Callout, Row, DefinitionList')
                ->line('Reference build', 'declarative-pdf'))
            ->page(fn ($p) => $p
                ->heading(1, 'Components')
                ->component(new Card(
                    [new Paragraph('framed body copy')],
                    title: 'Card title',
                    background: Color::rgb(248, 249, 251),
                ))
                ->component(new Callout('a tinted aside', title: 'Note'))
                ->component(new Callout('accent on top', accentEdge: Edge::Top))
                ->component(new Row([
                    new Paragraph('left', new StylePatch(spaceAfterPt: 0.0)),
                    new Paragraph('right', new StylePatch(spaceAfterPt: 0.0)),
                ], gapPt: 10.0, widths: [1 => ColumnWidth::fraction(1.0)]))
                ->component(new DefinitionList([
                    'Engine' => 'measure, paginate, render, serialise',
                    'Units' => 'points internally',
                ])))
            ->toString();
    }

    public function test_renders_byte_for_byte_to_the_golden(): void
    {
        Golden::assert('flow-components.pdf', $this->document());
    }

    public function test_the_rendered_text_carries_every_component(): void
    {
        $text = Pdf::contentText($this->document());

        foreach (['Flow components', 'Card title', 'framed body copy', 'Note', 'accent on top', 'Engine', 'Units'] as $needle) {
            self::assertStringContainsString("({$needle}) Tj", $text);
        }
    }

    public function test_the_cover_is_its_own_first_page(): void
    {
        $doc = Document::create()->using(Pdf::deterministicRenderer())
            ->cover(fn ($c) => $c->title('Cover'))
            ->page(fn ($p) => $p->paragraph('body'))
            ->build();

        self::assertCount(2, $doc->pages);
    }
}
