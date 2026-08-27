<?php

declare(strict_types=1);

namespace Pdf\Geometry;

/**
 * User-space measurement unit.
 *
 * Ports FPDF's scale-factor selection in the constructor (fpdf.php:112-119),
 * where `$k` is the number of PostScript points in one user unit.
 */
enum Unit: string
{
    case Pt = 'pt';
    case Mm = 'mm';
    case Cm = 'cm';
    case In = 'in';

    /** Number of PostScript points in one unit of this kind. */
    public function pointsPerUnit(): float
    {
        return match ($this) {
            self::Pt => 1.0,
            self::Mm => 72.0 / 25.4,
            self::Cm => 72.0 / 2.54,
            self::In => 72.0,
        };
    }

    public function toPoints(float $value): float
    {
        return $value * $this->pointsPerUnit();
    }

    public function fromPoints(float $points): float
    {
        return $points / $this->pointsPerUnit();
    }
}
