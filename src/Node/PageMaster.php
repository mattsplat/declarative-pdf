<?php

declare(strict_types=1);

namespace Pdf\Node;

use Pdf\Geometry\Edges;
use Pdf\Geometry\Orientation;
use Pdf\Geometry\PageGeometry;
use Pdf\Geometry\PageSize;
use Pdf\Geometry\Unit;
use Pdf\Layout\PageContext;

/**
 * The physical template for a page: size, orientation, margins and optional
 * header/footer.
 *
 * Replaces the orientation/size/margin arguments of the FPDF constructor
 * (fpdf.php:75) and `SetMargins()` (fpdf.php:168). The header/footer closures
 * replace subclassing `Header()` / `Footer()` (fpdf.php:356-364); each returns
 * block content and receives a {@see PageContext}.
 *
 * @phpstan-type BandFactory \Closure(PageContext): (BlockNode|iterable<BlockNode>)
 */
final readonly class PageMaster
{
    /** @var BandFactory|null */
    public ?\Closure $header;

    /** @var BandFactory|null */
    public ?\Closure $footer;

    /**
     * @param BandFactory|null $header
     * @param BandFactory|null $footer
     */
    public function __construct(
        public PageSize $size = new PageSize(595.28, 841.89),
        public Orientation $orientation = Orientation::Portrait,
        public Edges $marginsPt = new Edges(28.35, 28.35, 28.35, 28.35),
        ?\Closure $header = null,
        ?\Closure $footer = null,
        public ?Watermark $watermark = null,
    ) {
        $this->header = $header;
        $this->footer = $footer;
    }

    public static function of(
        PageSize $size,
        Orientation $orientation = Orientation::Portrait,
        float $margin = 10.0,
        Unit $marginUnit = Unit::Mm,
    ): self {
        return new self($size, $orientation, Edges::all($marginUnit->toPoints($margin)));
    }

    public function withHeader(\Closure $header): self
    {
        return new self($this->size, $this->orientation, $this->marginsPt, $header, $this->footer, $this->watermark);
    }

    public function withFooter(\Closure $footer): self
    {
        return new self($this->size, $this->orientation, $this->marginsPt, $this->header, $footer, $this->watermark);
    }

    public function withWatermark(Watermark $watermark): self
    {
        return new self($this->size, $this->orientation, $this->marginsPt, $this->header, $this->footer, $watermark);
    }

    public function geometry(): PageGeometry
    {
        return new PageGeometry(
            $this->size->forOrientation($this->orientation),
            $this->orientation,
            $this->marginsPt,
        );
    }
}
