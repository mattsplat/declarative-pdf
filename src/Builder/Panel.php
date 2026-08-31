<?php

declare(strict_types=1);

namespace Pdf\Builder;

use Pdf\Color\Color;
use Pdf\Geometry\BoxAlign;
use Pdf\Geometry\Fit;
use Pdf\Geometry\Rect;
use Pdf\Node\BlockNode;
use Pdf\Style\Border;

/**
 * One framed region of a sheet: a thin border with content fitted inside a
 * small inset. The content is a single page of a source PDF, a raster image,
 * or a run of block nodes — dispatched by {@see self::drawOn()} on the source.
 *
 * A named, self-describing wrapper around {@see PageBuilder::frame()} plus one
 * of {@see PageBuilder::placePdf()} / {@see PageBuilder::placeImage()} /
 * {@see PageBuilder::place()}. Build one, then draw it onto the page:
 *
 *   Panel::in($grid->rect())
 *       ->showing($schedulePdf)
 *       ->drawOn($p);
 *
 * {@see self::at()} takes coordinates in the page builder's units;
 * {@see self::in()} takes a {@see Rect} in points (as produced by
 * {@see \Pdf\Layout\Grid}) and converts to the builder's units when drawn. The
 * border width is in points, as everywhere in {@see Border}. Every
 * configuration method returns a new instance, so a base panel can be shared
 * and specialised per region.
 */
final readonly class Panel
{
    /**
     * @param iterable<BlockNode>|null $blocks
     */
    private function __construct(
        private float $x,
        private float $y,
        private float $width,
        private float $height,
        private string $source = '',
        private int $page = 1,
        private ?iterable $blocks = null,
        private float $inset = 3.0,
        private Fit $fit = Fit::Contain,
        private BoxAlign $align = BoxAlign::Center,
        private ?Border $border = null,
        private bool $pointSpace = false,
    ) {
    }

    /** A panel occupying the given absolute rectangle, in the page builder's units. */
    public static function at(float $x, float $y, float $width, float $height): self
    {
        return new self($x, $y, $width, $height);
    }

    /** A panel occupying a {@see Rect} in points — pairs with {@see \Pdf\Layout\Grid}. */
    public static function in(Rect $rect): self
    {
        return new self($rect->x, $rect->y, $rect->width, $rect->height, pointSpace: true);
    }

    /** The source PDF or image (and, for a PDF, the page) to drop inside the frame. */
    public function showing(string $path, int $page = 1): self
    {
        return new self(
            $this->x, $this->y, $this->width, $this->height,
            $path, $page, null,
            $this->inset, $this->fit, $this->align, $this->border, $this->pointSpace,
        );
    }

    /**
     * Block content to lay out inside the frame.
     *
     * @param iterable<BlockNode> $blocks
     */
    public function containing(iterable $blocks): self
    {
        return new self(
            $this->x, $this->y, $this->width, $this->height,
            '', 1, $blocks,
            $this->inset, $this->fit, $this->align, $this->border, $this->pointSpace,
        );
    }

    /** Gap between the frame and the placed content (same units as the panel, default 3). */
    public function inset(float $gap): self
    {
        return new self(
            $this->x, $this->y, $this->width, $this->height,
            $this->source, $this->page, $this->blocks,
            $gap, $this->fit, $this->align, $this->border, $this->pointSpace,
        );
    }

    /** How an image or PDF source is fitted within the inset area (ignored for blocks). */
    public function fitted(Fit $fit, BoxAlign $align = BoxAlign::Center): self
    {
        return new self(
            $this->x, $this->y, $this->width, $this->height,
            $this->source, $this->page, $this->blocks,
            $this->inset, $fit, $align, $this->border, $this->pointSpace,
        );
    }

    /** Override the frame border (default 0.75pt, 30% grey). */
    public function framed(Border $border): self
    {
        return new self(
            $this->x, $this->y, $this->width, $this->height,
            $this->source, $this->page, $this->blocks,
            $this->inset, $this->fit, $this->align, $border, $this->pointSpace,
        );
    }

    /** Draw the frame and the placed content onto the page builder. */
    public function drawOn(PageBuilder $p): void
    {
        if ($this->source === '' && $this->blocks === null) {
            throw new \LogicException('Panel::showing() or ->containing() must be called before drawOn().');
        }

        $unit = $p->unit();
        $x = $this->pointSpace ? $unit->fromPoints($this->x) : $this->x;
        $y = $this->pointSpace ? $unit->fromPoints($this->y) : $this->y;
        $w = $this->pointSpace ? $unit->fromPoints($this->width) : $this->width;
        $h = $this->pointSpace ? $unit->fromPoints($this->height) : $this->height;

        $p->frame($x, $y, $w, $h, $this->border ?? Border::uniform(0.75, Color::gray(30)));

        $gap = $this->inset;
        $this->drawContent($p, $x + $gap, $y + $gap, $w - $gap * 2, $h - $gap * 2);
    }

    private function drawContent(PageBuilder $p, float $x, float $y, float $width, float $height): void
    {
        if ($this->blocks !== null) {
            $p->place($x, $y, $width, $height, $this->blocks, $this->align);

            return;
        }

        $extension = strtolower(pathinfo($this->source, PATHINFO_EXTENSION));
        if ($extension === 'pdf') {
            $p->placePdf($x, $y, $width, $height, $this->source, $this->page, $this->fit, $this->align);

            return;
        }

        if (in_array($extension, ['png', 'jpg', 'jpeg', 'gif', 'webp', 'bmp'], true)) {
            $p->placeImage($x, $y, $width, $height, $this->source, $this->fit, $this->align);

            return;
        }

        throw new \LogicException("Panel cannot place source with extension '{$extension}'; expected a PDF or an image.");
    }
}
