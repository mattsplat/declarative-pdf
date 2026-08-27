<?php

declare(strict_types=1);

namespace Pdf\Node;

use Pdf\Geometry\Unit;
use Pdf\Style\StylePatch;

/**
 * Fixed vertical whitespace. Replaces a bare `Ln($h)` (fpdf.php:859).
 */
final readonly class Spacer implements BlockNode
{
    public function __construct(public float $heightPt)
    {
    }

    public static function of(float $height, Unit $unit = Unit::Mm): self
    {
        return new self($unit->toPoints($height));
    }

    public function patch(): StylePatch
    {
        return new StylePatch();
    }
}
