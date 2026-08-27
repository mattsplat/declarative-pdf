<?php

declare(strict_types=1);

namespace Pdf\Geometry;

use Pdf\Exception\PdfException;

/**
 * A physical page size, stored in PostScript points.
 *
 * Ports FPDF's `$StdPageSizes` table (fpdf.php:123) and the
 * "portrait means the shorter side is the width" normalisation in
 * `_getpagesize()` (fpdf.php:1073).
 */
final readonly class PageSize
{
    public function __construct(
        public float $widthPt,
        public float $heightPt,
    ) {
        if ($widthPt <= 0.0 || $heightPt <= 0.0) {
            throw new PdfException('Page dimensions must be positive.');
        }
    }

    public static function fromUnits(float $width, float $height, Unit $unit): self
    {
        return new self($unit->toPoints($width), $unit->toPoints($height));
    }

    public static function a3(): self
    {
        return new self(841.89, 1190.55);
    }

    public static function a4(): self
    {
        return new self(595.28, 841.89);
    }

    public static function a5(): self
    {
        return new self(420.94, 595.28);
    }

    public static function letter(): self
    {
        return new self(612.0, 792.0);
    }

    public static function legal(): self
    {
        return new self(612.0, 1008.0);
    }

    public static function a2(): self
    {
        return new self(1190.55, 1683.78);
    }

    public static function a1(): self
    {
        return new self(1683.78, 2383.94);
    }

    public static function a0(): self
    {
        return new self(2383.94, 3370.39);
    }

    public static function tabloid(): self
    {
        return new self(792.0, 1224.0);
    }

    /** ANSI/ASME sheet A–E (A = Letter, E = 34x44"). */
    public static function ansi(string $sheet): self
    {
        return match (strtolower($sheet)) {
            'a' => self::letter(),
            'b' => self::tabloid(),
            'c' => self::fromUnits(17, 22, Unit::In),
            'd' => self::fromUnits(22, 34, Unit::In),
            'e' => self::fromUnits(34, 44, Unit::In),
            default => throw new PdfException('Unknown ANSI sheet: ' . $sheet),
        };
    }

    /** Architectural sheet A–E (A = 9x12", E = 36x48"). */
    public static function arch(string $sheet): self
    {
        return match (strtolower($sheet)) {
            'a' => self::fromUnits(9, 12, Unit::In),
            'b' => self::fromUnits(12, 18, Unit::In),
            'c' => self::fromUnits(18, 24, Unit::In),
            'd' => self::fromUnits(24, 36, Unit::In),
            'e' => self::fromUnits(36, 48, Unit::In),
            'e1' => self::fromUnits(30, 42, Unit::In),
            default => throw new PdfException('Unknown architectural sheet: ' . $sheet),
        };
    }

    /** Resolve a named size ("a4", "letter", ...); mirrors `_getpagesize()`. */
    public static function named(string $name): self
    {
        return match (strtolower($name)) {
            'a0' => self::a0(),
            'a1' => self::a1(),
            'a2' => self::a2(),
            'a3' => self::a3(),
            'a4' => self::a4(),
            'a5' => self::a5(),
            'letter' => self::letter(),
            'legal' => self::legal(),
            'tabloid', 'ledger' => self::tabloid(),
            default => throw new PdfException('Unknown page size: ' . $name),
        };
    }

    /** The size as it appears on the page for the given orientation. */
    public function forOrientation(Orientation $orientation): self
    {
        $short = min($this->widthPt, $this->heightPt);
        $long = max($this->widthPt, $this->heightPt);

        return $orientation === Orientation::Portrait
            ? new self($short, $long)
            : new self($long, $short);
    }

    public function equals(self $other): bool
    {
        return abs($this->widthPt - $other->widthPt) < 1e-6
            && abs($this->heightPt - $other->heightPt) < 1e-6;
    }
}
