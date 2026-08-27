<?php

declare(strict_types=1);

namespace Pdf\Node;

use Pdf\Geometry\Unit;
use Pdf\Style\StylePatch;
use Pdf\Style\TextAlign;

/**
 * A block-level image loaded from a file.
 *
 * Sizing mirrors `Image()` (fpdf.php:896-910): with no explicit dimensions the
 * intrinsic size is the pixel size at `dpi` (default 96); one dimension is
 * derived from the other to preserve the aspect ratio; an image wider than the
 * content box is scaled down to fit.
 */
final readonly class ImageBlock implements BlockNode
{
    public function __construct(
        public string $path,
        public ?float $widthPt = null,
        public ?float $heightPt = null,
        public TextAlign $align = TextAlign::Left,
        public float $dpi = 96.0,
        private StylePatch $patch = new StylePatch(),
    ) {
    }

    public static function of(
        string $path,
        ?float $width = null,
        ?float $height = null,
        Unit $unit = Unit::Mm,
        TextAlign $align = TextAlign::Left,
    ): self {
        return new self(
            $path,
            $width !== null ? $unit->toPoints($width) : null,
            $height !== null ? $unit->toPoints($height) : null,
            $align,
        );
    }

    public function patch(): StylePatch
    {
        return $this->patch;
    }
}
