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
 * {@see self::at()} takes coordinates — and an {@see self::inset()} gap — in the
 * page builder's units. {@see self::in()} takes a {@see Rect} in points (as
 * produced by {@see \Pdf\Layout\Grid}); its coordinates and inset are in points
 * and are converted to the builder's units when drawn. The border width is in
 * points, as everywhere in {@see Border}. Every configuration method returns a
 * new instance, so a base panel can be shared and specialised per region.
 */
final readonly class Panel
{
    private const IMAGE_EXTENSIONS = ['png', 'jpg', 'jpeg', 'gif', 'webp', 'bmp'];

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
        private ?BoxAlign $align = null,
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

    /** Gap between the frame and the placed content, in the panel's own units (default 3). */
    public function inset(float $gap): self
    {
        return new self(
            $this->x, $this->y, $this->width, $this->height,
            $this->source, $this->page, $this->blocks,
            $gap, $this->fit, $this->align, $this->border, $this->pointSpace,
        );
    }

    /**
     * How an image or PDF source is fitted within the inset area (`$fit` has no
     * effect on block content). A null `$align` keeps the per-content default:
     * {@see BoxAlign::Center} for an image or PDF, {@see BoxAlign::TopLeft} for
     * blocks.
     */
    public function fitted(Fit $fit, ?BoxAlign $align = null): self
    {
        return new self(
            $this->x, $this->y, $this->width, $this->height,
            $this->source, $this->page, $this->blocks,
            $this->inset, $fit, $align ?? $this->align, $this->border, $this->pointSpace,
        );
    }

    /** Align the content within the inset area, overriding the per-content default. */
    public function aligned(BoxAlign $align): self
    {
        return new self(
            $this->x, $this->y, $this->width, $this->height,
            $this->source, $this->page, $this->blocks,
            $this->inset, $this->fit, $align, $this->border, $this->pointSpace,
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

        $toUnit = $this->pointSpace
            ? static fn (float $points): float => $p->unit()->fromPoints($points)
            : static fn (float $value): float => $value;

        $x = $toUnit($this->x);
        $y = $toUnit($this->y);
        $w = $toUnit($this->width);
        $h = $toUnit($this->height);
        $gap = $toUnit($this->inset);

        $p->frame($x, $y, $w, $h, $this->border ?? Border::uniform(0.75, Color::gray(30)));

        $this->drawContent($p, $x + $gap, $y + $gap, $w - $gap * 2, $h - $gap * 2);
    }

    private function drawContent(PageBuilder $p, float $x, float $y, float $width, float $height): void
    {
        if ($this->blocks !== null) {
            $p->place($x, $y, $width, $height, $this->blocks, $this->align ?? BoxAlign::TopLeft);

            return;
        }

        if ($this->sourceIsPdf()) {
            $p->placePdf($x, $y, $width, $height, $this->source, $this->page, $this->fit, $this->align ?? BoxAlign::Center);

            return;
        }

        if ($this->sourceIsImage()) {
            $p->placeImage($x, $y, $width, $height, $this->source, $this->fit, $this->align ?? BoxAlign::Center);

            return;
        }

        throw new \LogicException("Panel cannot place source '{$this->source}'; expected a PDF or an image.");
    }

    private function sourceIsPdf(): bool
    {
        if (str_starts_with($this->source, 'data:')) {
            return str_starts_with($this->dataUriMediaType(), 'application/pdf');
        }

        return $this->sourceExtension() === 'pdf';
    }

    private function sourceIsImage(): bool
    {
        if (str_starts_with($this->source, 'data:')) {
            return str_starts_with($this->dataUriMediaType(), 'image/');
        }

        return in_array($this->sourceExtension(), self::IMAGE_EXTENSIONS, true);
    }

    /** The lower-cased extension of the source, with any URL query/fragment stripped. */
    private function sourceExtension(): string
    {
        $path = preg_replace('/[?#].*$/', '', $this->source) ?? $this->source;

        return strtolower(pathinfo($path, PATHINFO_EXTENSION));
    }

    /** The media type of a `data:` URI source, lower-cased — e.g. `image/png`. */
    private function dataUriMediaType(): string
    {
        $comma = strpos($this->source, ',');
        $header = $comma === false ? substr($this->source, 5) : substr($this->source, 5, $comma - 5);

        return strtolower(trim(explode(';', $header, 2)[0]));
    }
}
