<?php

declare(strict_types=1);

namespace Pdf\Style;

/**
 * What a {@see Gradient} does outside the segment its stops describe — the
 * `/Extend` array of a `/Shading` (PDF 32000-1 §8.7.4.3).
 */
enum GradientSpread
{
    /** Hold the end colours, so the gradient covers the whole clip region. */
    case Pad;

    /** Paint nothing beyond the first and last stop. */
    case None;

    /** The two booleans of the shading's `/Extend` array. */
    public function extendArray(): string
    {
        return $this === self::Pad ? 'true true' : 'false false';
    }
}
