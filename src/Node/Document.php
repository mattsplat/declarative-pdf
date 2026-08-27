<?php

declare(strict_types=1);

namespace Pdf\Node;

use Pdf\Style\Style;
use Pdf\Style\Stylesheet;

/**
 * The root of the document tree: metadata, a base style and one or more pages.
 */
final readonly class Document
{
    /** @var list<Page> */
    public array $pages;

    /** @param iterable<Page> $pages */
    public function __construct(
        iterable $pages,
        public Meta $meta = new Meta(),
        public ?Style $baseStyle = null,
        public ?Stylesheet $stylesheet = null,
    ) {
        $this->pages = is_array($pages) ? array_values($pages) : iterator_to_array($pages, false);
    }

    public function style(): Style
    {
        return $this->baseStyle ?? Style::default();
    }
}
