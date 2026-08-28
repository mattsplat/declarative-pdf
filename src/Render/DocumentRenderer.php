<?php

declare(strict_types=1);

namespace Pdf\Render;

use Pdf\Font\FontRegistry;
use Pdf\Font\FontRepository;
use Pdf\Geometry\PageGeometry;
use Pdf\Image\ImageFactory;
use Pdf\Layout\AnchorMark;
use Pdf\Layout\LineBreaker;
use Pdf\Layout\LinkRect;
use Pdf\Layout\Measurer;
use Pdf\Layout\Paginator;
use Pdf\Layout\PhysicalPage;
use Pdf\Node\Document;
use Pdf\Style\StyleResolver;
use Pdf\Support\Clock;
use Pdf\Support\SystemClock;

/**
 * Runs the full pipeline: paginate every page, render each sheet's content
 * (collecting links and anchors), then serialise the PDF.
 *
 * Object numbering and buffer ordering mirror `_enddoc()` / `_putpages()` /
 * `_putresources()` (fpdf.php:1948, 1616, 1895): per-page objects (page dict,
 * content stream, then one annotation per link), the pages tree as object 1,
 * fonts, images, the resource dictionary as object 2, info, catalog.
 */
final class DocumentRenderer
{
    public function __construct(
        private readonly FontRepository $fontRepository,
        private readonly Clock $clock = new SystemClock(),
        private readonly bool $compress = true,
        private readonly string $producer = 'mattsplat/declarative-pdf',
    ) {
    }

    public static function default(): self
    {
        return new self(FontRepository::withBundledFonts());
    }

    public function render(Document $document): string
    {
        $fonts = new FontRegistry($this->fontRepository);
        $measurer = new Measurer(new StyleResolver($document->stylesheet), $fonts, new LineBreaker(), new ImageFactory());
        $paginator = new Paginator($measurer);

        $pages = $paginator->paginate($document);

        return $this->serialise($document, $pages, $fonts, $measurer);
    }

    /**
     * @param list<PhysicalPage> $pages
     */
    private function serialise(Document $document, array $pages, FontRegistry $fonts, Measurer $measurer): string
    {
        $images = $measurer->imageRegistry();
        $imports = $measurer->importRegistry();

        // --- Pass A: render content streams, collect links + anchors ---
        /** @var list<array{geometry: PageGeometry, content: string, links: list<LinkRect>, anchors: list<AnchorMark>}> $rendered */
        $rendered = [];
        foreach ($pages as $page) {
            $stream = new ContentStream($page->geometry);
            $content = $page->geometry->contentBox();
            $page->header?->render($stream, $content->x, $page->headerTopPt, $content->width);
            $page->body->render($stream, $content->x, $page->bodyTopPt, $content->width);
            $page->footer?->render($stream, $content->x, $page->footerTopPt, $content->width);

            foreach ($page->areas as $area) {
                $this->renderArea($stream, $page->geometry, $area);
            }

            $rendered[] = [
                'geometry' => $page->geometry,
                'content' => $stream->toString(),
                'links' => $stream->collectedLinks(),
                'anchors' => $stream->collectedAnchors(),
            ];
        }

        // --- Anchor map: name -> [pageIndex, yTopPt] ---
        /** @var array<string, array{0: int, 1: float}> $anchorMap */
        $anchorMap = [];
        foreach ($rendered as $index => $r) {
            foreach ($r['anchors'] as $anchor) {
                $anchorMap[$anchor->name] ??= [$index, $anchor->yTopPt];
            }
        }

        $registry = new ObjectRegistry(2);
        $writer = new PdfWriter($registry, $this->compress);
        $writer->header($images->requiresPdf14() || !$imports->isEmpty() ? '1.4' : '1.3');
        $withAlpha = $images->hasAlpha();

        // --- Pre-allocate per-page object numbers, in FPDF's order ---
        $pageObjects = [];
        $contentObjects = [];
        /** @var list<list<int>> $linkObjects */
        $linkObjects = [];
        foreach ($rendered as $index => $r) {
            $pageObjects[$index] = $registry->allocate();
            $contentObjects[$index] = $registry->allocate();
            $linkObjects[$index] = array_map(static fn () => $registry->allocate(), $r['links']);
        }

        $defaultGeometry = $rendered[0]['geometry'];

        // --- Write pages, content, annotations ---
        foreach ($rendered as $index => $r) {
            $writer->beginObject($pageObjects[$index]);
            $writer->line('<</Type /Page');
            $writer->line('/Parent 1 0 R');
            if (!$this->sameSize($r['geometry'], $defaultGeometry)) {
                $writer->line(sprintf(
                    '/MediaBox [0 0 %.2F %.2F]',
                    $r['geometry']->widthPt(),
                    $r['geometry']->heightPt(),
                ));
            }
            $writer->line('/Resources 2 0 R');
            if ($withAlpha) {
                $writer->line('/Group <</Type /Group /S /Transparency /CS /DeviceRGB>>');
            }
            if ($linkObjects[$index] !== []) {
                $refs = implode(' ', array_map(static fn (int $n) => $n . ' 0 R', $linkObjects[$index]));
                $writer->line('/Annots [' . $refs . ']');
            }
            $writer->line('/Contents ' . $contentObjects[$index] . ' 0 R>>');
            $writer->endObject();

            $writer->streamObjectAt($contentObjects[$index], $r['content']);

            foreach ($r['links'] as $k => $linkRect) {
                $this->writeAnnotation(
                    $writer,
                    $linkObjects[$index][$k],
                    $r['geometry'],
                    $linkRect,
                    $anchorMap,
                    $pageObjects,
                    $rendered,
                );
            }
        }

        // Pages tree (object 1).
        $writer->beginObject(1);
        $writer->line('<</Type /Pages');
        $writer->line('/Kids [' . implode(' ', array_map(static fn (int $n) => $n . ' 0 R', $pageObjects)) . ']');
        $writer->line('/Count ' . count($pageObjects));
        $writer->line(sprintf(
            '/MediaBox [0 0 %.2F %.2F]',
            $defaultGeometry->widthPt(),
            $defaultGeometry->heightPt(),
        ));
        $writer->line('>>');
        $writer->endObject();

        // Fonts and images.
        (new FontWriter($writer))->write($fonts->used());
        (new ImageWriter($writer))->write($images->used());
        (new FormXObjectWriter($writer))->write($imports->used());

        // Resource dictionary (object 2).
        $writer->beginObject(2);
        $writer->line('<<');
        $writer->line('/ProcSet [/PDF /Text /ImageB /ImageC /ImageI]');
        $writer->line('/Font <<');
        foreach ($fonts->used() as $font) {
            $writer->line('/F' . $font->index . ' ' . $font->objectNumber . ' 0 R');
        }
        $writer->line('>>');
        $writer->line('/XObject <<');
        foreach ($images->used() as $image) {
            $writer->line('/I' . $image->index . ' ' . $image->objectNumber . ' 0 R');
        }
        foreach ($imports->used() as $import) {
            $writer->line('/Import' . $import->index . ' ' . $import->objectNumber . ' 0 R');
        }
        $writer->line('>>');
        $writer->line('>>');
        $writer->endObject();

        // Info dictionary.
        $infoObject = $writer->beginObject();
        $writer->line('<<');
        $writer->line('/Producer ' . PdfString::text($this->producer));
        foreach ($document->meta->entries() as $key => $value) {
            $writer->line('/' . $key . ' ' . PdfString::text($value));
        }
        $writer->line('/CreationDate ' . PdfString::text($this->creationDate()));
        $writer->line('>>');
        $writer->endObject();

        // Catalog.
        $catalogObject = $writer->beginObject();
        $writer->line('<<');
        $writer->line('/Type /Catalog');
        $writer->line('/Pages 1 0 R');
        $writer->line('>>');
        $writer->endObject();

        $writer->finish($catalogObject, $infoObject);

        return $writer->toBytes();
    }

    /**
     * @param array<string, array{0: int, 1: float}>                                                        $anchorMap
     * @param list<int>                                                                                     $pageObjects
     * @param list<array{geometry: PageGeometry, content: string, links: list<LinkRect>, anchors: list<AnchorMark>}> $rendered
     */
    private function writeAnnotation(
        PdfWriter $writer,
        int $objectNumber,
        PageGeometry $geometry,
        LinkRect $link,
        array $anchorMap,
        array $pageObjects,
        array $rendered,
    ): void {
        $x1 = $link->xPt;
        $x2 = $link->xPt + $link->widthPt;
        $yTop = $geometry->flipY($link->yTopPt);
        $yBottom = $geometry->flipY($link->yTopPt + $link->heightPt);

        $writer->beginObject($objectNumber);
        $dict = sprintf(
            '<</Type /Annot /Subtype /Link /Rect [%.2F %.2F %.2F %.2F] /Border [0 0 0] ',
            $x1,
            $yBottom,
            $x2,
            $yTop,
        );

        if ($link->isInternal()) {
            $target = $anchorMap[$link->anchorName()] ?? null;
            if ($target !== null) {
                [$pageIndex, $destY] = $target;
                $destGeometry = $rendered[$pageIndex]['geometry'];
                $dict .= sprintf(
                    '/Dest [%d 0 R /XYZ 0 %.2F null]>>',
                    $pageObjects[$pageIndex],
                    $destGeometry->flipY($destY),
                );
            } else {
                $dict .= '>>';
            }
        } else {
            $dict .= '/A <</S /URI /URI ' . PdfString::text($link->target) . '>>>>';
        }

        $writer->line($dict);
        $writer->endObject();
    }

    private function renderArea(ContentStream $stream, PageGeometry $geometry, \Pdf\Layout\PlacedArea $area): void
    {
        $rect = $area->rectPt;
        $rw = $rect->width;
        $rh = $rect->height;
        if ($rw <= 0.0 || $rh <= 0.0) {
            return;
        }

        if ($area->frameBorder !== null || $area->frameBackground !== null) {
            if ($area->frameBackground !== null) {
                $stream->fillRect($rect->x, $rect->y, $rw, $rh, $area->frameBackground);
            }
            if ($area->frameBorder !== null && $area->frameBorder->isVisible()) {
                $stream->strokeEdges($rect->x, $rect->y, $rw, $rh, $area->frameBorder->widthPt, $area->frameBorder->color);
            }
            return;
        }

        if ($area->blocks !== null) {
            $this->renderBlockArea($stream, $geometry, $area);
            return;
        }

        if ($area->importIndex !== null && $area->importPage !== null) {
            $this->renderImportArea($stream, $geometry, $area);
            return;
        }

        if ($area->imageIndex === null) {
            return;
        }

        [$sx, $sy] = $area->fit->scale($area->sourceWidthPt, $area->sourceHeightPt, $rw, $rh);
        $drawnW = $sx * $area->sourceWidthPt;
        $drawnH = $sy * $area->sourceHeightPt;
        $x = $rect->x + $area->align->horizontalFraction() * ($rw - $drawnW);
        $y = $rect->y + $area->align->verticalFraction() * ($rh - $drawnH);

        $index = $area->imageIndex;
        if ($area->fit->clips()) {
            $stream->withClip($rect->x, $rect->y, $rw, $rh, static function () use ($stream, $index, $x, $y, $drawnW, $drawnH): void {
                $stream->image($index, $x, $y, $drawnW, $drawnH);
            });
        } else {
            $stream->image($index, $x, $y, $drawnW, $drawnH);
        }
    }

    private function renderImportArea(ContentStream $stream, PageGeometry $geometry, \Pdf\Layout\PlacedArea $area): void
    {
        $page = $area->importPage;
        if ($page === null || $area->importIndex === null) {
            return;
        }

        $rect = $area->rectPt;
        $rw = $rect->width;
        $rh = $rect->height;
        [$sx, $sy] = $area->fit->scale($area->sourceWidthPt, $area->sourceHeightPt, $rw, $rh);
        $drawnW = $sx * $area->sourceWidthPt;
        $drawnH = $sy * $area->sourceHeightPt;
        $destXTop = $rect->x + $area->align->horizontalFraction() * ($rw - $drawnW);
        $destYTop = $rect->y + $area->align->verticalFraction() * ($rh - $drawnH);
        $destYBottom = $geometry->flipY($destYTop + $drawnH);

        [$llx, $lly] = [$page->boundingBox[0], $page->boundingBox[1]];
        $bw = $page->boxWidthPt();
        $bh = $page->boxHeightPt();

        // form content -> origin -> rotate -> back to first quadrant -> scale -> place
        $m = [1.0, 0.0, 0.0, 1.0, -$llx, -$lly];
        $m = self::concat($this->rotationMatrix($page->rotation), $m);
        $m = self::concat([1.0, 0.0, 0.0, 1.0, ...$this->rotationAdjust($page->rotation, $bw, $bh)], $m);
        $m = self::concat([$sx, 0.0, 0.0, $sy, 0.0, 0.0], $m);
        $m = self::concat([1.0, 0.0, 0.0, 1.0, $destXTop, $destYBottom], $m);

        $index = $area->importIndex;
        $draw = static function () use ($stream, $index, $m): void {
            $stream->formXObject($index, $m[0], $m[1], $m[2], $m[3], $m[4], $m[5]);
        };

        if ($area->fit->clips()) {
            $stream->withClip($rect->x, $rect->y, $rw, $rh, $draw);
        } else {
            $draw();
        }
    }

    /** @return array{0: float, 1: float, 2: float, 3: float, 4: float, 5: float} */
    private function rotationMatrix(int $rotation): array
    {
        return match ($rotation % 360) {
            90 => [0.0, 1.0, -1.0, 0.0, 0.0, 0.0],
            180 => [-1.0, 0.0, 0.0, -1.0, 0.0, 0.0],
            270 => [0.0, -1.0, 1.0, 0.0, 0.0, 0.0],
            default => [1.0, 0.0, 0.0, 1.0, 0.0, 0.0],
        };
    }

    /** @return array{0: float, 1: float} */
    private function rotationAdjust(int $rotation, float $bw, float $bh): array
    {
        return match ($rotation % 360) {
            90 => [$bh, 0.0],
            180 => [$bw, $bh],
            270 => [0.0, $bw],
            default => [0.0, 0.0],
        };
    }

    /**
     * Concatenate 2-D affine matrices: apply $inner, then $outer.
     *
     * @param array{0: float, 1: float, 2: float, 3: float, 4: float, 5: float} $outer
     * @param array{0: float, 1: float, 2: float, 3: float, 4: float, 5: float} $inner
     * @return array{0: float, 1: float, 2: float, 3: float, 4: float, 5: float}
     */
    private static function concat(array $outer, array $inner): array
    {
        [$a2, $b2, $c2, $d2, $e2, $f2] = $outer;
        [$a1, $b1, $c1, $d1, $e1, $f1] = $inner;

        return [
            $a2 * $a1 + $c2 * $b1,
            $b2 * $a1 + $d2 * $b1,
            $a2 * $c1 + $c2 * $d1,
            $b2 * $c1 + $d2 * $d1,
            $a2 * $e1 + $c2 * $f1 + $e2,
            $b2 * $e1 + $d2 * $f1 + $f2,
        ];
    }

    private function renderBlockArea(ContentStream $stream, PageGeometry $geometry, \Pdf\Layout\PlacedArea $area): void
    {
        $rect = $area->rectPt;
        $contentHeight = max($area->blocksNaturalHeightPt, 0.0);
        $scale = ($area->blocksGeometricShrink && $contentHeight > 0.0 && $contentHeight > $rect->height)
            ? $rect->height / $contentHeight
            : 1.0;

        $drawnHeight = $scale * $contentHeight;
        $drawnWidth = $scale * $rect->width;
        $originX = $rect->x + $area->align->horizontalFraction() * ($rect->width - $drawnWidth);
        $originYTop = $rect->y + $area->align->verticalFraction() * ($rect->height - $drawnHeight);

        $sub = new ContentStream(new PageGeometry(
            new \Pdf\Geometry\PageSize($rect->width, max(1.0, $contentHeight)),
            \Pdf\Geometry\Orientation::Portrait,
            new \Pdf\Geometry\Edges(),
        ), emitPreamble: false);
        $area->blocks?->render($sub, 0.0, 0.0, $rect->width);

        $ty = $geometry->flipY($originYTop + $scale * $contentHeight);
        $stream->raw(sprintf('q %.4F 0 0 %.4F %.2F %.2F cm', $scale, $scale, $originX, $ty));
        $stream->raw($sub->toString());
        $stream->raw('Q');

        foreach ($sub->collectedLinks() as $link) {
            $stream->link(
                $originX + $scale * $link->xPt,
                $originYTop + $scale * $link->yTopPt,
                $scale * $link->widthPt,
                $scale * $link->heightPt,
                $link->target,
            );
        }
        foreach ($sub->collectedAnchors() as $anchor) {
            $stream->anchor($anchor->name, $originYTop + $scale * $anchor->yTopPt);
        }
    }

    private function sameSize(PageGeometry $a, PageGeometry $b): bool
    {
        return abs($a->widthPt() - $b->widthPt()) < 1e-6
            && abs($a->heightPt() - $b->heightPt()) < 1e-6;
    }

    private function creationDate(): string
    {
        $formatted = $this->clock->now()->format('YmdHisO');

        return 'D:' . substr($formatted, 0, -2) . "'" . substr($formatted, -2) . "'";
    }
}
