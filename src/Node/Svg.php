<?php

declare(strict_types=1);

namespace Pdf\Node;

use Pdf\Geometry\Unit;
use Pdf\Style\StylePatch;
use Pdf\Style\TextAlign;

/**
 * A block-level SVG, drawn as vector linework rather than rasterised, so it
 * stays sharp at any zoom.
 *
 * Sizing mirrors {@see ImageBlock}: with no explicit dimensions the SVG's own
 * `width` / `height` (or its `viewBox`) give the intrinsic size, one dimension
 * derives the other preserving aspect ratio, and anything wider than the
 * content box is scaled down to fit.
 *
 * Markup held in memory goes in as a `data:` URI — see {@see self::fromString()}.
 */
final readonly class Svg implements BlockNode
{
    public function __construct(
        public string $source,
        public ?float $widthPt = null,
        public ?float $heightPt = null,
        public TextAlign $align = TextAlign::Left,
        private StylePatch $patch = new StylePatch(),
    ) {
    }

    /** A path, `http(s)://` URL or `data:` URI, sized in `$unit`. */
    public static function of(
        string $source,
        ?float $width = null,
        ?float $height = null,
        Unit $unit = Unit::Mm,
        TextAlign $align = TextAlign::Left,
    ): self {
        return new self(
            $source,
            $width !== null ? $unit->toPoints($width) : null,
            $height !== null ? $unit->toPoints($height) : null,
            $align,
        );
    }

    /** SVG markup already in memory, carried inline as a `data:` URI. */
    public static function fromString(
        string $markup,
        ?float $width = null,
        ?float $height = null,
        Unit $unit = Unit::Mm,
        TextAlign $align = TextAlign::Left,
    ): self {
        return self::of(
            'data:image/svg+xml;base64,' . base64_encode($markup),
            $width,
            $height,
            $unit,
            $align,
        );
    }

    public function patch(): StylePatch
    {
        return $this->patch;
    }
}
