<?php

declare(strict_types=1);

namespace Pdf\Builder;

use Pdf\Color\Color;
use Pdf\Geometry\BoxAlign;
use Pdf\Geometry\Fit;
use Pdf\Style\Border;

/**
 * One framed region of a sheet: a thin border with a single page of a source
 * PDF fitted inside a small inset.
 *
 * A named, self-describing wrapper around the {@see PageBuilder::frame()} +
 * {@see PageBuilder::placePdf()} pair. Build one, then draw it onto the page:
 *
 *   Panel::at($margin, $margin, $mainW, $scheduleH)
 *       ->showing($schedulePdf)
 *       ->drawOn($p);
 *
 * Coordinates, size and inset are in the page builder's units; the border
 * width is in points (as everywhere in {@see Border}). Every configuration
 * method returns a new instance, so a base panel can be shared and specialised
 * per region.
 */
final readonly class Panel
{
    private function __construct(
        private float $x,
        private float $y,
        private float $width,
        private float $height,
        private string $source = '',
        private int $page = 1,
        private float $inset = 3.0,
        private Fit $fit = Fit::Contain,
        private BoxAlign $align = BoxAlign::Center,
        private ?Border $border = null,
    ) {
    }

    /** A panel occupying the given absolute rectangle (page builder units). */
    public static function at(float $x, float $y, float $width, float $height): self
    {
        return new self($x, $y, $width, $height);
    }

    /** The source PDF (and optional page) to drop inside the frame. */
    public function showing(string $path, int $page = 1): self
    {
        return new self(
            $this->x,
            $this->y,
            $this->width,
            $this->height,
            $path,
            $page,
            $this->inset,
            $this->fit,
            $this->align,
            $this->border,
        );
    }

    /** Gap between the frame and the placed page (page builder units, default 3). */
    public function inset(float $gap): self
    {
        return new self(
            $this->x,
            $this->y,
            $this->width,
            $this->height,
            $this->source,
            $this->page,
            $gap,
            $this->fit,
            $this->align,
            $this->border,
        );
    }

    /** How the source page is fitted within the inset area. */
    public function fitted(Fit $fit, BoxAlign $align = BoxAlign::Center): self
    {
        return new self(
            $this->x,
            $this->y,
            $this->width,
            $this->height,
            $this->source,
            $this->page,
            $this->inset,
            $fit,
            $align,
            $this->border,
        );
    }

    /** Override the frame border (default 0.75pt, 30% grey). */
    public function framed(Border $border): self
    {
        return new self(
            $this->x,
            $this->y,
            $this->width,
            $this->height,
            $this->source,
            $this->page,
            $this->inset,
            $this->fit,
            $this->align,
            $border,
        );
    }

    /** Draw the frame and the placed source page onto the page builder. */
    public function drawOn(PageBuilder $p): void
    {
        if ($this->source === '') {
            throw new \LogicException('Panel::showing() must be called before drawOn().');
        }

        $p->frame(
            $this->x,
            $this->y,
            $this->width,
            $this->height,
            $this->border ?? Border::uniform(0.75, Color::gray(30)),
        );

        $gap = $this->inset;
        $p->placePdf(
            $this->x + $gap,
            $this->y + $gap,
            $this->width - $gap * 2,
            $this->height - $gap * 2,
            $this->source,
            $this->page,
            $this->fit,
            $this->align,
        );
    }
}
