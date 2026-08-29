<?php

declare(strict_types=1);

namespace Pdf\Tests\Functional;

use Pdf\Color\Color;
use Pdf\Document;
use Pdf\Exception\LayoutException;
use Pdf\Geometry\Edges;
use Pdf\Node\BlockNode;
use Pdf\Node\Component;
use Pdf\Node\Paragraph;
use Pdf\Node\Rule;
use Pdf\Style\Border;
use Pdf\Style\StylePatch;
use Pdf\Tests\Support\DemoCallout;
use Pdf\Tests\Support\DemoCard;
use Pdf\Tests\Support\Pdf;
use PHPUnit\Framework\TestCase;

final class ComponentTest extends TestCase
{
    public function test_component_body_is_the_expansion_and_is_testable_without_rendering(): void
    {
        self::assertInstanceOf(Paragraph::class, (new DemoCallout('Hi'))->body());
    }

    public function test_a_slot_component_yields_title_rule_then_content(): void
    {
        $nodes = iterator_to_array(
            (new DemoCard('Totals', [new Paragraph('one'), new Paragraph('two')]))->body(),
            false,
        );

        self::assertCount(4, $nodes);
        self::assertInstanceOf(Paragraph::class, $nodes[0]);
        self::assertInstanceOf(Rule::class, $nodes[1]);
    }

    public function test_a_component_renders_byte_identically_to_its_hand_written_expansion(): void
    {
        $renderer = Pdf::deterministicRenderer();

        $viaComponent = Document::create()->using($renderer)
            ->page(fn ($p) => $p->component(new DemoCallout('Hi')))
            ->toString();

        $viaHand = Document::create()->using($renderer)
            ->page(fn ($p) => $p->container(
                [new Paragraph('Hi', new StylePatch(fontSizePt: 10))],
                new StylePatch(
                    paddingPt: Edges::all(8),
                    background: Color::rgb(255, 245, 150),
                    border: Border::uniform(0.5, Color::gray(180)),
                ),
            ))
            ->toString();

        self::assertSame($viaHand, $viaComponent);
    }

    public function test_components_nest_and_compose_with_a_container(): void
    {
        $pdf = Document::create()->using(Pdf::deterministicRenderer())
            ->page(fn ($p) => $p->container(
                [new DemoCard('Outer', [new DemoCallout('inner')])],
                new StylePatch(spaceBeforePt: 20),
            ))
            ->toString();

        $text = Pdf::contentText($pdf);
        self::assertStringContainsString('(Outer) Tj', $text);
        self::assertStringContainsString('(inner) Tj', $text);
    }

    public function test_a_component_with_no_patch_splices_its_body_in_byte_identically(): void
    {
        $renderer = Pdf::deterministicRenderer();

        $wrapper = new readonly class extends Component {
            public function body(): iterable
            {
                yield new Paragraph('alpha', new StylePatch(bold: true));
                yield new Paragraph('beta');
            }
        };

        $viaComponent = Document::create()->using($renderer)
            ->page(fn ($p) => $p->paragraph('before')->component($wrapper)->paragraph('after'))
            ->toString();

        $viaHand = Document::create()->using($renderer)
            ->page(fn ($p) => $p
                ->paragraph('before')
                ->paragraph('alpha', new StylePatch(bold: true))
                ->paragraph('beta')
                ->paragraph('after'))
            ->toString();

        self::assertSame($viaHand, $viaComponent);
    }

    public function test_a_cyclic_component_fails_loud(): void
    {
        $cyclic = new readonly class extends Component {
            public function body(): BlockNode
            {
                return $this;
            }
        };

        $this->expectException(LayoutException::class);

        Document::create()->using(Pdf::deterministicRenderer())
            ->page(fn ($p) => $p->component($cyclic))
            ->toString();
    }
}
