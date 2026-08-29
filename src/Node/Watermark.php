<?php

declare(strict_types=1);

namespace Pdf\Node;

use Pdf\Color\Color;
use Pdf\Font\FontFace;

/**
 * A word or short phrase stamped across every physical sheet — "DRAFT",
 * "CONFIDENTIAL", a copy number.
 *
 * It is not part of the flow: it draws once per sheet, centred on the whole
 * page (not the content box), rotated, and — by default — translucently on top
 * of everything else. `new Watermark('DRAFT')` is a sensible default; the rest
 * of the constructor tunes it.
 */
final readonly class Watermark
{
    public function __construct(
        public string $text,
        public Color $color = new Color(120, 120, 120),
        /** 0 (invisible) to 1 (opaque). Below 1 emits an `/ExtGState`. */
        public float $opacity = 0.12,
        public float $angleDeg = 45.0,
        /** `null` auto-sizes the text to ~85% of the page diagonal. */
        public ?float $fontSizePt = null,
        public string $fontFamily = 'Helvetica',
        public FontFace $fontFace = new FontFace(700),
        /** `true` draws over the content, `false` behind it. */
        public bool $overlay = true,
    ) {
    }
}
