<?php

declare(strict_types=1);

namespace Pdf\Layout;

use Pdf\Exception\LayoutException;
use Pdf\Geometry\BoxAlign;
use Pdf\Geometry\Rect;
use Pdf\Geometry\ShrinkMode;
use Pdf\Layout\Box\StackBox;
use Pdf\Node\BlockNode;
use Pdf\Node\Document;
use Pdf\Node\Page;
use Pdf\Node\Placement\Blocks;
use Pdf\Node\Placement\Frame;
use Pdf\Node\Placement\PdfPage;
use Pdf\Node\Placement\Picture;
use Pdf\Style\Style;
use Pdf\Style\StylePatch;

/**
 * Flows each logical {@see Page} across as many physical sheets as its content
 * needs, then places headers and footers with document-wide page numbers.
 *
 * Replaces FPDF's implicit page breaking (`Cell()` at fpdf.php:584, `AddPage()`
 * at fpdf.php:287) and the `{nb}` total-pages alias (fpdf.php:258): the whole
 * document is laid out before any header/footer runs, so the real page count is
 * known.
 */
final class Paginator
{
    /** Gap between a header/footer band and the body. */
    private const BAND_GAP_PT = 6.0;

    private const MAX_PAGES = 20_000;

    public function __construct(private readonly Measurer $measurer)
    {
    }

    /** @return list<PhysicalPage> */
    public function paginate(Document $document): array
    {
        $documentStyle = $document->style();

        // Pass 1: an estimate using single-digit page numbers in the bands.
        $logical = $this->layoutAll($document, $documentStyle, 1);
        $totalPages = $this->countPages($logical);

        // Pass 2: if headers/footers exist, re-lay-out reserving space for the
        // real (widest) page numbers — "Page 340 of 340" may wrap where
        // "Page 1 of 1" did not.
        if ($totalPages > 9 && $this->hasBands($document)) {
            $logical = $this->layoutAll($document, $documentStyle, $totalPages);
            $totalPages = $this->countPages($logical);
        }

        /** @var list<PhysicalPage> $physical */
        $physical = [];
        $pageNumber = 0;

        foreach ($logical as $entry) {
            $page = $entry['page'];
            $geometry = $page->master->geometry();
            $content = $geometry->contentBox();
            $pageStyle = $page->patch->applyTo($documentStyle);

            foreach ($entry['bodies'] as $slot => $body) {
                $pageNumber++;
                $context = new PageContext($pageNumber, $totalPages, $entry['contentWidth']);

                $header = $page->master->header !== null
                    ? $this->measureBand($page->master->header, $context, $entry['contentWidth'], $pageStyle)
                    : null;
                $footer = $page->master->footer !== null
                    ? $this->measureBand($page->master->footer, $context, $entry['contentWidth'], $pageStyle)
                    : null;

                $footerHeight = $footer?->contentHeightPt() ?? 0.0;

                $physical[] = new PhysicalPage(
                    geometry: $geometry,
                    body: $body,
                    bodyTopPt: $entry['bodyTop'],
                    header: $header,
                    headerTopPt: $content->y,
                    footer: $footer,
                    footerTopPt: $content->bottom() - $footerHeight,
                    bodyOverflowed: $entry['overflow'][$slot],
                    // Placements go on the first physical sheet of the logical page.
                    areas: $slot === 0 ? $this->resolveAreas($page, $pageStyle) : [],
                );
            }
        }

        if ($physical === []) {
            $physical[] = $this->blankPage($document);
        }

        return $physical;
    }

    /**
     * @param list<array{page: Page, bodies: list<StackBox>, overflow: list<bool>, bodyTop: float, headerTop: float, footerReserve: float, contentWidth: float}> $logical
     */
    private function countPages(array $logical): int
    {
        $total = 0;
        foreach ($logical as $entry) {
            $total += count($entry['bodies']);
        }

        return max(1, $total);
    }

    private function hasBands(Document $document): bool
    {
        foreach ($document->pages as $page) {
            if ($page->master->header !== null || $page->master->footer !== null) {
                return true;
            }
        }

        return false;
    }

    /**
     * @return list<array{page: Page, bodies: list<StackBox>, overflow: list<bool>, bodyTop: float, headerTop: float, footerReserve: float, contentWidth: float}>
     */
    private function layoutAll(Document $document, Style $documentStyle, int $bandPageNumber): array
    {
        $logical = [];
        foreach ($document->pages as $page) {
            $logical[] = $this->layoutLogicalPage($page, $documentStyle, $bandPageNumber);
        }

        return $logical;
    }

    /**
     * @return array{page: Page, bodies: list<StackBox>, overflow: list<bool>, bodyTop: float, headerTop: float, footerReserve: float, contentWidth: float}
     */
    private function layoutLogicalPage(Page $page, Style $documentStyle, int $bandPageNumber): array
    {
        $geometry = $page->master->geometry();
        $content = $geometry->contentBox();
        $pageStyle = $page->patch->applyTo($documentStyle);

        $bandContext = new PageContext($bandPageNumber, $bandPageNumber, $content->width);
        $headerProto = $page->master->header !== null
            ? $this->measureBand($page->master->header, $bandContext, $content->width, $pageStyle)
            : null;
        $footerProto = $page->master->footer !== null
            ? $this->measureBand($page->master->footer, $bandContext, $content->width, $pageStyle)
            : null;

        $headerReserve = $headerProto !== null ? $headerProto->contentHeightPt() + self::BAND_GAP_PT : 0.0;
        $footerReserve = $footerProto !== null ? $footerProto->contentHeightPt() + self::BAND_GAP_PT : 0.0;

        $bodyTop = $content->y + $headerReserve;
        $bodyAvailable = $content->height - $headerReserve - $footerReserve;
        if ($bodyAvailable <= 0.0) {
            throw new LayoutException('Header and footer leave no room for page content.');
        }

        $bodyStack = $this->measurer->measureStack($page->children, $content->width, $pageStyle);

        /** @var list<StackBox> $bodies */
        $bodies = [];
        /** @var list<bool> $overflow */
        $overflow = [];
        $remaining = $bodyStack;

        while ($remaining !== null && !$remaining->isEmpty()) {
            if (count($bodies) > self::MAX_PAGES) {
                throw new LayoutException('Pagination did not terminate; a box may be larger than a page and unsplittable.');
            }

            [$head, $tail] = $remaining->split($bodyAvailable);

            if ($head === null) {
                $first = $remaining->first();
                $forced = new StackBox($first !== null ? [$first] : []);
                $bodies[] = $forced;
                $overflow[] = $forced->contentHeightPt() > $bodyAvailable + 1e-4;
                $remaining = $remaining->withoutFirst();
                continue;
            }

            $bodies[] = $head;
            $overflow[] = false;
            $remaining = $tail;
        }

        if ($bodies === []) {
            $bodies[] = new StackBox([]);
            $overflow[] = false;
        }

        return [
            'page' => $page,
            'bodies' => $bodies,
            'overflow' => $overflow,
            'bodyTop' => $bodyTop,
            'headerTop' => $content->y,
            'footerReserve' => $footerReserve,
            'contentWidth' => $content->width,
        ];
    }

    /**
     * @return list<PlacedArea>
     */
    private function resolveAreas(Page $page, Style $pageStyle): array
    {
        /** @var list<PlacedArea> $areas */
        $areas = [];
        foreach ($page->placements as $placement) {
            $rect = $placement->rectPt;
            $content = $placement->content;

            if ($content instanceof Frame) {
                $areas[] = PlacedArea::forFrame($rect, $content->border, $content->background);
                continue;
            }

            if ($content instanceof Blocks) {
                $areas[] = $this->placeBlocks($rect, $placement->align, $content, $pageStyle);
                continue;
            }

            if ($content instanceof Picture) {
                $resource = $this->measurer->images()->fromPath($content->path);
                $resolved = $this->measurer->imageRegistry()->use($resource);
                $areas[] = PlacedArea::forImage(
                    $rect,
                    $placement->fit,
                    $placement->align,
                    $resolved->index,
                    $resource->widthPx * 72.0 / 96.0,
                    $resource->heightPx * 72.0 / 96.0,
                );
                continue;
            }

            if ($content instanceof PdfPage) {
                $resolved = $this->measurer->importRegistry()->use($content->path, $content->page);
                $areas[] = PlacedArea::forImport(
                    $rect,
                    $placement->fit,
                    $placement->align,
                    $resolved->index,
                    $resolved->page,
                );
                continue;
            }

            throw new LayoutException('Unsupported placement content: ' . $content::class);
        }

        return $areas;
    }

    /**
     * Measure a placed {@see Blocks} area, applying its {@see ShrinkMode}:
     * `Scale` / `None` emit the natural stack (the renderer handles or skips
     * the geometric shrink); `FontSize` re-flows at a smaller effective font
     * size, falling back to `Scale` only if the 0.5 floor still overflows.
     */
    private function placeBlocks(Rect $rect, BoxAlign $align, Blocks $content, Style $pageStyle): PlacedArea
    {
        $widthPt = max(0.0, $rect->width);
        $stack = $this->measurer->measureStack($content->blocks, $widthPt, $pageStyle);
        $geometricShrink = $content->shrink !== ShrinkMode::None;

        if ($content->shrink !== ShrinkMode::FontSize
            || $rect->height <= 0.0
            || $stack->contentHeightPt() <= $rect->height
        ) {
            return PlacedArea::forBlocks($rect, $align, $stack, $geometricShrink);
        }

        $fitted = $this->fitByFontSize($content->blocks, $widthPt, $rect->height, $pageStyle);

        return PlacedArea::forBlocks($rect, $align, $fitted ?? $stack, true);
    }

    /**
     * Binary-search a font-size factor in [0.5, 1.0] (six iterations, ~0.8%
     * precision) that makes the stack fit $targetHeightPt. Returns the fitted
     * stack, or null when even the 0.5 floor overflows.
     *
     * @param list<BlockNode> $blocks
     */
    private function fitByFontSize(array $blocks, float $widthPt, float $targetHeightPt, Style $pageStyle): ?StackBox
    {
        $lo = 0.5;
        $hi = 1.0;
        for ($i = 0; $i < 6; $i++) {
            $mid = ($lo + $hi) / 2.0;
            if ($this->measureScaled($blocks, $widthPt, $pageStyle, $mid)->contentHeightPt() <= $targetHeightPt) {
                $lo = $mid;
            } else {
                $hi = $mid;
            }
        }

        $fitted = $this->measureScaled($blocks, $widthPt, $pageStyle, $lo);

        return $fitted->contentHeightPt() <= $targetHeightPt ? $fitted : null;
    }

    /**
     * @param list<BlockNode> $blocks
     */
    private function measureScaled(array $blocks, float $widthPt, Style $pageStyle, float $factor): StackBox
    {
        $scaledParent = (new StylePatch(fontSizePt: $pageStyle->fontSizePt * $factor))->applyTo($pageStyle);

        return $this->measurer->withFontScale($factor)->measureStack($blocks, $widthPt, $scaledParent);
    }

    /**
     * @param \Closure(PageContext): (BlockNode|iterable<BlockNode>) $factory
     */
    private function measureBand(\Closure $factory, PageContext $context, float $widthPt, Style $parentStyle): StackBox
    {
        $result = $factory($context);
        $nodes = $result instanceof BlockNode ? [$result] : $result;

        return $this->measurer->measureStack($nodes, $widthPt, $parentStyle);
    }

    private function blankPage(Document $document): PhysicalPage
    {
        $page = new Page();
        $geometry = $page->master->geometry();
        $content = $geometry->contentBox();

        return new PhysicalPage(
            geometry: $geometry,
            body: $this->measurer->measureStack([], $content->width, $document->style()),
            bodyTopPt: $content->y,
            header: null,
            headerTopPt: $content->y,
            footer: null,
            footerTopPt: $content->bottom(),
            bodyOverflowed: false,
        );
    }
}
