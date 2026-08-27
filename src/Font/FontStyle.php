<?php

declare(strict_types=1);

namespace Pdf\Font;

/**
 * The four selectable weights/slopes of a font family.
 *
 * The file-suffix mapping ('', 'b', 'i', 'bi') mirrors FPDF's default font
 * file naming in `AddFont()` (fpdf.php:448) and the `IB` -> `BI` normalisation
 * in `SetFont()` (fpdf.php:490).
 */
enum FontStyle
{
    case Regular;
    case Bold;
    case Italic;
    case BoldItalic;

    public function fileSuffix(): string
    {
        return match ($this) {
            self::Regular => '',
            self::Bold => 'b',
            self::Italic => 'i',
            self::BoldItalic => 'bi',
        };
    }

    public function isBold(): bool
    {
        return $this === self::Bold || $this === self::BoldItalic;
    }

    public function isItalic(): bool
    {
        return $this === self::Italic || $this === self::BoldItalic;
    }

    public static function of(bool $bold, bool $italic): self
    {
        return match (true) {
            $bold && $italic => self::BoldItalic,
            $bold => self::Bold,
            $italic => self::Italic,
            default => self::Regular,
        };
    }
}
