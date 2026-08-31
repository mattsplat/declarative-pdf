<?php

declare(strict_types=1);

namespace Pdf\Node;

use Pdf\Color\Color;
use Pdf\Exception\PdfException;
use Pdf\Geometry\Edges;
use Pdf\Style\Border;
use Pdf\Style\StylePatch;
use Pdf\Text\InlineSequence;

/**
 * A framed panel: an optional title (with a hairline rule under it) above a
 * slot of block content, wrapped in padding, a border and an optional
 * background.
 */
final readonly class Card extends Component
{
    /** @var list<BlockNode> */
    public array $content;

    /**
     * `$border` defaults to a hairline frame; pass {@see Border::none()} to drop
     * it. `$paddingPt` / `$background` fall back to a 12 pt inset and no fill.
     *
     * @param iterable<BlockNode> $content
     */
    public function __construct(
        iterable $content,
        public InlineSequence|string|null $title = null,
        public bool $rule = true,
        public ?Edges $paddingPt = null,
        public ?Border $border = null,
        public ?Color $background = null,
        public StylePatch $titleStyle = new StylePatch(bold: true, fontSizePt: 13.0, spaceAfterPt: 4.0),
    ) {
        $this->content = is_array($content) ? array_values($content) : iterator_to_array($content, false);
        if ($this->content === []) {
            throw new PdfException('A card needs some content.');
        }
    }

    public function body(): iterable
    {
        if ($this->title !== null) {
            yield new Paragraph($this->title, $this->titleStyle);
            if ($this->rule) {
                yield new Rule(0.5, Color::gray(200));
            }
        }

        yield from $this->content;
    }

    public function patch(): StylePatch
    {
        return new StylePatch(
            paddingPt: $this->paddingPt ?? Edges::all(12.0),
            border: $this->border ?? Border::uniform(0.75),
            background: $this->background,
        );
    }
}
