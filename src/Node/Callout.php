<?php

declare(strict_types=1);

namespace Pdf\Node;

use Pdf\Color\Color;
use Pdf\Exception\PdfException;
use Pdf\Geometry\Edges;
use Pdf\Style\Border;
use Pdf\Style\Edge;
use Pdf\Style\StylePatch;
use Pdf\Text\InlineSequence;

/**
 * A tinted aside: text or blocks on a background wash, with an accent border on
 * one edge and an optional bold title. Splits across pages like any container.
 */
final readonly class Callout extends Component
{
    /** @var list<BlockNode> */
    public array $content;

    /**
     * @param string|InlineSequence|iterable<BlockNode> $content
     */
    public function __construct(
        string|InlineSequence|iterable $content,
        public InlineSequence|string|null $title = null,
        public Color $tint = new Color(244, 247, 252),
        public Color $accent = new Color(64, 120, 200),
        public Edge $accentEdge = Edge::Left,
        public float $accentWidthPt = 3.0,
        public Edges $paddingPt = new Edges(10.0, 12.0, 10.0, 12.0),
        public StylePatch $titleStyle = new StylePatch(bold: true, spaceAfterPt: 3.0),
    ) {
        if (is_string($content) || $content instanceof InlineSequence) {
            $this->content = [new Paragraph($content)];
        } else {
            $this->content = is_array($content) ? array_values($content) : iterator_to_array($content, false);
        }

        if ($this->content === []) {
            throw new PdfException('A callout needs some content.');
        }
    }

    public function body(): iterable
    {
        if ($this->title !== null) {
            yield new Paragraph($this->title, $this->titleStyle);
        }

        yield from $this->content;
    }

    public function patch(): StylePatch
    {
        return new StylePatch(
            paddingPt: $this->paddingPt,
            border: new Border($this->accentEdge->only($this->accentWidthPt), $this->accent),
            background: $this->tint,
        );
    }
}
