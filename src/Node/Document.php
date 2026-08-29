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

    /** @var list<Bookmark> */
    public array $bookmarks;

    /**
     * @param iterable<Page> $pages
     * @param iterable<Bookmark> $bookmarks
     */
    public function __construct(
        iterable $pages,
        public Meta $meta = new Meta(),
        public ?Style $baseStyle = null,
        public ?Stylesheet $stylesheet = null,
        iterable $bookmarks = [],
    ) {
        $this->pages = is_array($pages) ? array_values($pages) : iterator_to_array($pages, false);
        $this->bookmarks = is_array($bookmarks) ? array_values($bookmarks) : iterator_to_array($bookmarks, false);
    }

    public function style(): Style
    {
        return $this->baseStyle ?? Style::default();
    }
}
