<?php

declare(strict_types=1);

namespace Pdf\Builder;

use Pdf\Color\Color;
use Pdf\Interactive\Js;
use Pdf\Node\Bookmark;
use Pdf\Node\Document;
use Pdf\Node\Meta;
use Pdf\Node\Page;
use Pdf\Node\Watermark;
use Pdf\Output\PdfOutput;
use Pdf\Render\DocumentRenderer;
use Pdf\Style\Style;
use Pdf\Style\Stylesheet;
use Pdf\Style\TextAlign;

/**
 * Fluent entry point for building a document tree and rendering it.
 *
 * Page configurator closures are stored, not run eagerly: each one is invoked
 * from {@see self::build()} / {@see self::toString()}, once the renderer (hence
 * the fonts) is known, so a closure can measure text and blocks
 * (`PageBuilder::textWidth()`, `PageBuilder::measureBlocks()`) as it builds.
 */
final class DocumentBuilder
{
    private Meta $meta;

    /** @var list<Page|CoverBuilder|(callable(PageBuilder): mixed)> */
    private array $pageSources = [];

    private ?Style $baseStyle = null;

    private ?Stylesheet $stylesheet = null;

    /** @var list<Bookmark> */
    private array $bookmarks = [];

    /** @var array<string, string> document-level JavaScript, name => source */
    private array $scripts = [];

    private ?DocumentRenderer $renderer = null;

    /**
     * Furniture applied to every page before its configurator runs. A cover
     * page opts in per `kind` via {@see CoverBuilder::wants()}.
     *
     * @var list<array{kind: string, apply: \Closure(PageBuilder): mixed}>
     */
    private array $pageDefaults = [];

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
        $this->pageSources[] = $configure;

        return $this;
    }

    /**
     * Prepend a cover page. `$configure` receives a {@see CoverBuilder} — a
     * title, subtitle, logo, caption lines, its own page size and one of the
     * {@see CoverLayout} presets. The cover keeps an inherited watermark but
     * drops inherited page numbers; {@see CoverBuilder::bare()} drops both.
     *
     * @param callable(CoverBuilder): mixed $configure
     */
    public function cover(callable $configure): self
    {
        $builder = new CoverBuilder();
        $configure($builder);
        array_unshift($this->pageSources, $builder);

        return $this;
    }

    public function addPage(Page $page): self
    {
        $this->pageSources[] = $page;

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

    /**
     * Stamp a word across every page built with `page()` — `'CONFIDENTIAL'`, or a
     * configured {@see Watermark}. A page may still override with its own
     * `PageBuilder::watermark()`.
     */
    public function watermark(string|Watermark $watermark): self
    {
        $this->pageDefaults[] = [
            'kind' => 'watermark',
            'apply' => static fn (PageBuilder $p) => $p->watermark($watermark),
        ];

        return $this;
    }

    /** Page numbers on every page built with `page()`; see {@see PageBuilder::pageNumbers()}. */
    public function pageNumbers(
        string $format = 'Page {n} of {N}',
        TextAlign $align = TextAlign::Center,
        float $fontSizePt = 9.0,
        ?Color $color = null,
        bool $inHeader = false,
    ): self {
        $this->pageDefaults[] = [
            'kind' => 'pageNumbers',
            'apply' => static fn (PageBuilder $p) => $p->pageNumbers(
                $format,
                $align,
                $fontSizePt,
                $color,
                $inHeader,
            ),
        ];

        return $this;
    }

    /**
     * Add an outline entry (a bookmark in the viewer's sidebar) pointing at an
     * existing {@see \Pdf\Node\Anchor}. `$level` 0 is top-level; deeper items
     * nest under the nearest preceding item of a lower level, in call order.
     */
    public function bookmark(string $title, string $anchor, int $level = 0): self
    {
        $this->bookmarks[] = new Bookmark($title, $anchor, $level);

        return $this;
    }

    /**
     * Register a document-level JavaScript function, run when the file opens.
     * Only Acrobat / Reader (and mostly Foxit) execute PDF JavaScript — see
     * {@see Js} for the full viewer-support caveat.
     */
    public function script(string $name, Js|string $js): self
    {
        $this->scripts[$name] = $js instanceof Js ? $js->source : $js;

        return $this;
    }

    public function using(DocumentRenderer $renderer): self
    {
        $this->renderer = $renderer;

        return $this;
    }

    public function build(): Document
    {
        return $this->buildWith($this->renderer ?? DocumentRenderer::default());
    }

    public function toString(): string
    {
        $renderer = $this->renderer ?? DocumentRenderer::default();

        return $renderer->render($this->buildWith($renderer));
    }

    public function output(): PdfOutput
    {
        return new PdfOutput($this->toString());
    }

    public function save(string $path): void
    {
        $this->output()->save($path);
    }

    private function buildWith(DocumentRenderer $renderer): Document
    {
        return new Document(
            $this->resolvePages($renderer),
            $this->meta,
            $this->baseStyle,
            $this->stylesheet,
            $this->bookmarks,
            $this->scripts,
        );
    }

    /** @return list<Page> */
    private function resolvePages(DocumentRenderer $renderer): array
    {
        $measurer = $renderer->newMeasurer($this->stylesheet);
        $baseStyle = $this->baseStyle ?? Style::default();

        $pages = [];
        foreach ($this->pageSources as $source) {
            if ($source instanceof Page) {
                $pages[] = $source;

                continue;
            }

            $builder = new PageBuilder($measurer, $baseStyle);

            if ($source instanceof CoverBuilder) {
                $this->applyPageDefaults($builder, $source);
                $source->configure($builder);
            } else {
                $this->applyPageDefaults($builder, null);
                $source($builder);
            }

            $pages[] = $builder->build();
        }

        return $pages;
    }

    private function applyPageDefaults(PageBuilder $builder, ?CoverBuilder $cover): void
    {
        foreach ($this->pageDefaults as $default) {
            if ($cover !== null && !$cover->wants($default['kind'])) {
                continue;
            }
            ($default['apply'])($builder);
        }
    }
}
