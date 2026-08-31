<?php

declare(strict_types=1);

namespace Pdf\Node;

use Pdf\Exception\PdfException;
use Pdf\Geometry\Edges;
use Pdf\Style\ColumnWidth;
use Pdf\Style\StylePatch;
use Pdf\Style\VerticalAlign;
use Pdf\Text\InlineSequence;

/**
 * Term / description pairs, laid out as a borderless two-column {@see Table}:
 * the term in a content-sized left column, its body wrapping in the rest of the
 * width. Replaces the hand-rolled label grids that recur across the examples.
 */
final readonly class DefinitionList extends Component
{
    /** @var list<array{0: string|InlineSequence, 1: string|InlineSequence|list<BlockNode>}> */
    public array $items;

    /**
     * `$items` is either a `term => body` map or a list of `[term, body]`
     * pairs; a body may be a string, an {@see InlineSequence} or block children.
     *
     * @param array<string, string|InlineSequence>|iterable<array{0: string|InlineSequence, 1: string|InlineSequence|iterable<BlockNode>}> $items
     */
    public function __construct(
        iterable $items,
        public ?ColumnWidth $termWidth = null,
        public StylePatch $termStyle = new StylePatch(bold: true),
        public StylePatch $bodyStyle = new StylePatch(),
    ) {
        $normalised = [];
        foreach ($items as $key => $value) {
            if (is_string($value) || $value instanceof InlineSequence) {
                $normalised[] = [(string) $key, $value];

                continue;
            }

            $body = $value[1];
            if (!is_string($body) && !$body instanceof InlineSequence) {
                $body = is_array($body) ? array_values($body) : iterator_to_array($body, false);
            }
            $normalised[] = [$value[0], $body];
        }

        if ($normalised === []) {
            throw new PdfException('A definition list needs at least one item.');
        }

        $this->items = $normalised;
    }

    public function body(): BlockNode
    {
        $rows = [];
        foreach ($this->items as [$term, $description]) {
            $rows[] = new TableRow([
                new TableCell($term, verticalAlign: VerticalAlign::Top, patch: $this->termStyle),
                new TableCell($description, verticalAlign: VerticalAlign::Top, patch: $this->bodyStyle),
            ]);
        }

        return new Table(
            $rows,
            [$this->termWidth ?? ColumnWidth::auto(), ColumnWidth::fraction(1.0)],
            borderWidthPt: 0.0,
            cellPaddingPt: new Edges(1.5, 8.0, 1.5, 0.0),
        );
    }
}
