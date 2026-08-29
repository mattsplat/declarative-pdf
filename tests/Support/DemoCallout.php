<?php

declare(strict_types=1);

namespace Pdf\Tests\Support;

use Pdf\Color\Color;
use Pdf\Geometry\Edges;
use Pdf\Node\BlockNode;
use Pdf\Node\Component;
use Pdf\Node\Paragraph;
use Pdf\Style\Border;
use Pdf\Style\StylePatch;

/**
 * A leaf {@see Component}: parameters in, tree out, box style via `patch()`.
 */
final readonly class DemoCallout extends Component
{
    public function __construct(
        private string $text,
        private Color $tint = new Color(255, 245, 150),
    ) {
    }

    public function body(): BlockNode
    {
        return new Paragraph($this->text, new StylePatch(fontSizePt: 10));
    }

    public function patch(): StylePatch
    {
        return new StylePatch(
            paddingPt: Edges::all(8),
            background: $this->tint,
            border: Border::uniform(0.5, Color::gray(180)),
        );
    }
}
