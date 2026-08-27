<?php

declare(strict_types=1);

namespace Pdf;

use Pdf\Builder\DocumentBuilder;
use Pdf\Node\Document as DocumentTree;
use Pdf\Render\DocumentRenderer;

/**
 * Public entry point.
 *
 *   Pdf\Document::create()
 *     ->meta(fn($m) => $m->title('Report'))
 *     ->page(fn($p) => $p->heading(2, 'Overview')->paragraph('...'))
 *     ->save('out.pdf');
 *
 * The immutable {@see \Pdf\Node\Document} tree can also be built directly and
 * passed to {@see self::render()}.
 */
final class Document
{
    public static function create(): DocumentBuilder
    {
        return new DocumentBuilder();
    }

    public static function render(DocumentTree $tree, ?DocumentRenderer $renderer = null): string
    {
        return ($renderer ?? DocumentRenderer::default())->render($tree);
    }
}
