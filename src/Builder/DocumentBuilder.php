<?php

declare(strict_types=1);

namespace Pdf\Builder;

use Pdf\Node\Document;
use Pdf\Node\Meta;
use Pdf\Node\Page;
use Pdf\Output\PdfOutput;
use Pdf\Render\DocumentRenderer;
use Pdf\Style\Style;
use Pdf\Style\Stylesheet;

/**
 * Fluent entry point for building a document tree and rendering it.
 */
final class DocumentBuilder
{
    private Meta $meta;

    /** @var list<Page> */
    private array $pages = [];

    private ?Style $baseStyle = null;

    private ?Stylesheet $stylesheet = null;

    private ?DocumentRenderer $renderer = null;

    public function __construct()
    {
        $this->meta = new Meta();
    }

    /** @param callable(MetaBuilder): mixed $configure */
    public function meta(callable $configure): self
    {
        $builder = new MetaBuilder();
        $configure($builder);
        $this->meta = $builder->build();

        return $this;
    }

    /** @param callable(PageBuilder): mixed $configure */
    public function page(callable $configure): self
    {
        $builder = new PageBuilder();
        $configure($builder);
        $this->pages[] = $builder->build();

        return $this;
    }

    public function addPage(Page $page): self
    {
        $this->pages[] = $page;

        return $this;
    }

    public function baseStyle(Style $style): self
    {
        $this->baseStyle = $style;

        return $this;
    }

    public function stylesheet(Stylesheet $stylesheet): self
    {
        $this->stylesheet = $stylesheet;

        return $this;
    }

    public function using(DocumentRenderer $renderer): self
    {
        $this->renderer = $renderer;

        return $this;
    }

    public function build(): Document
    {
        return new Document($this->pages, $this->meta, $this->baseStyle, $this->stylesheet);
    }

    public function toString(): string
    {
        return ($this->renderer ?? DocumentRenderer::default())->render($this->build());
    }

    public function output(): PdfOutput
    {
        return new PdfOutput($this->toString());
    }

    public function save(string $path): void
    {
        $this->output()->save($path);
    }
}
