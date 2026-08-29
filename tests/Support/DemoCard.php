<?php

declare(strict_types=1);

namespace Pdf\Tests\Support;

use Pdf\Color\Color;
use Pdf\Geometry\Edges;
use Pdf\Node\BlockNode;
use Pdf\Node\Component;
use Pdf\Node\Paragraph;
use Pdf\Node\Rule;
use Pdf\Style\Border;
use Pdf\Style\StylePatch;

/**
 * A {@see Component} with a slot: it takes child blocks and frames them.
 */
final readonly class DemoCard extends Component
{
    /** @param iterable<BlockNode> $content */
    public function __construct(
        private string $title,
        private iterable $content,
    ) {
    }

    public function body(): iterable
    {
        yield new Paragraph($this->title, new StylePatch(bold: true, fontSizePt: 13, spaceAfterPt: 4));
        yield new Rule(0.5, Color::gray(200));
        yield from $this->content;
    }

    public function patch(): StylePatch
    {
        return new StylePatch(paddingPt: Edges::all(12), border: Border::uniform(0.75));
    }
}
