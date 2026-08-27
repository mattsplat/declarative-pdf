<?php

declare(strict_types=1);

namespace Pdf\Color;

use Pdf\Exception\PdfException;

/**
 * An RGB colour with 0-255 components.
 *
 * `strokeOp()` / `fillOp()` / `textOp()` produce the same operator strings as
 * FPDF's `SetDrawColor()` (fpdf.php:372), `SetFillColor()` (fpdf.php:383) and
 * `SetTextColor()` (fpdf.php:395): a grey operator when r == g == b, otherwise
 * an RGB operator.
 */
final readonly class Color
{
    public function __construct(
        public int $r,
        public int $g,
        public int $b,
    ) {
        foreach ([$r, $g, $b] as $component) {
            if ($component < 0 || $component > 255) {
                throw new PdfException('Colour components must be between 0 and 255.');
            }
        }
    }

    public static function rgb(int $r, int $g, int $b): self
    {
        return new self($r, $g, $b);
    }

    public static function gray(int $level): self
    {
        return new self($level, $level, $level);
    }

    public static function black(): self
    {
        return new self(0, 0, 0);
    }

    public static function white(): self
    {
        return new self(255, 255, 255);
    }

    public static function fromHex(string $hex): self
    {
        $hex = ltrim($hex, '#');
        if (strlen($hex) === 3) {
            $hex = $hex[0] . $hex[0] . $hex[1] . $hex[1] . $hex[2] . $hex[2];
        }
        if (strlen($hex) !== 6 || !ctype_xdigit($hex)) {
            throw new PdfException('Invalid hex colour: ' . $hex);
        }

        return new self(
            (int) hexdec(substr($hex, 0, 2)),
            (int) hexdec(substr($hex, 2, 2)),
            (int) hexdec(substr($hex, 4, 2)),
        );
    }

    public function isGray(): bool
    {
        return $this->r === $this->g && $this->g === $this->b;
    }

    public function equals(self $other): bool
    {
        return $this->r === $other->r && $this->g === $other->g && $this->b === $other->b;
    }

    public function strokeOp(): string
    {
        return $this->isGray()
            ? sprintf('%.3F G', $this->r / 255)
            : sprintf('%.3F %.3F %.3F RG', $this->r / 255, $this->g / 255, $this->b / 255);
    }

    public function fillOp(): string
    {
        return $this->isGray()
            ? sprintf('%.3F g', $this->r / 255)
            : sprintf('%.3F %.3F %.3F rg', $this->r / 255, $this->g / 255, $this->b / 255);
    }

    /** Text fill uses the same operators as non-stroking fill. */
    public function textOp(): string
    {
        return $this->fillOp();
    }
}
