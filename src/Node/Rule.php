<?php

declare(strict_types=1);

namespace Pdf\Node;

use Pdf\Color\Color;
use Pdf\Style\StylePatch;

/**
 * A horizontal rule spanning the content width. Ports a manual `Line()`
 * (fpdf.php:425) used as a separator.
 */
final readonly class Rule implements BlockNode
{
    public function __construct(
        public float $thicknessPt = 0.5,
        public ?Color $color = null,
        private StylePatch $patch = new StylePatch(),
    ) {
    }

    public function patch(): StylePatch
    {
        return $this->patch;
    }
}
