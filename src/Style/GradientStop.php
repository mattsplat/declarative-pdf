<?php

declare(strict_types=1);

namespace Pdf\Style;

use Pdf\Color\Color;
use Pdf\Exception\PdfException;

/**
 * One colour stop of a {@see Gradient}: a colour at a position along the
 * gradient axis, `$offset` running from 0 (start) to 1 (end).
 */
final readonly class GradientStop
{
    public function __construct(
        public float $offset,
        public Color $color,
    ) {
        if ($offset < 0.0 || $offset > 1.0) {
            throw new PdfException('A gradient stop offset must be between 0 and 1.');
        }
    }

    public static function at(float $offset, Color $color): self
    {
        return new self($offset, $color);
    }
}
