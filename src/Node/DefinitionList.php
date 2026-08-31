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
     * `$items` is either a `term => body` map — where each body must be a string
     * or {@see InlineSequence} — or a list of `[term, body]` pairs, where a body
     * may additionally be block children. The input is loosely typed because it
     * is validated here: a map body that is neither a string nor an
     * {@see InlineSequence} raises a {@see PdfException}.
     *
     * @param iterable<array-key, mixed> $items
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

            if (
                !is_array($value)
                || !array_key_exists(0, $value)
                || !array_key_exists(1, $value)
                || (!is_string($value[0]) && !$value[0] instanceof InlineSequence)
            ) {
                throw new PdfException(
                    'A map-form definition body must be a string or InlineSequence; '
                    . 'use the [term, body] pair form for block content.',
                );
            }

            $term = $value[0];
            $body = $value[1];
            if (!is_string($body) && !$body instanceof InlineSequence) {
                $body = is_iterable($body)
                    ? $this->blocks($body)
                    : throw new PdfException('A pair-form body must be a string, an InlineSequence or block children.');
            }
            $normalised[] = [$term, $body];
        }

        if ($normalised === []) {
            throw new PdfException('A definition list needs at least one item.');
        }

        $this->items = $normalised;
    }

    /**
     * @param iterable<mixed> $nodes
     * @return list<BlockNode>
     */
    private function blocks(iterable $nodes): array
    {
        $out = [];
        foreach ($nodes as $node) {
            if (!$node instanceof BlockNode) {
                throw new PdfException('A pair-form block body must contain only block nodes.');
            }
            $out[] = $node;
        }

        return $out;
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
