<?php

declare(strict_types=1);

namespace Pdf\Interactive;

use Pdf\Render\PdfString;
use Pdf\Text\Encoding;

/**
 * Builds the content-stream bytes for a widget's `/AP /N` appearance — the
 * border, the background fill and the value text — so the field renders
 * identically in every viewer instead of relying on `/NeedAppearances`.
 *
 * Coordinates here are local to the widget's own form space (`/BBox [0 0 w h]`,
 * bottom-left origin). This is a self-contained coordinate system, not page
 * space, so the page Y-flip ({@see \Pdf\Geometry\PageGeometry::flipY()}) does
 * not apply and must not be used.
 */
final class AppearanceStream
{
    /** Inset text this far from the border, matching Acrobat's default. */
    private const PADDING_PT = 2.0;

    private function __construct()
    {
    }

    /** The concrete point size for a widget of height $h, honouring auto-size (0.0). */
    public static function resolveFontSize(float $requested, float $heightPt, bool $multiline): float
    {
        if ($requested > 0.0) {
            return $requested;
        }
        if ($multiline) {
            return 10.0;
        }

        return max(4.0, min(12.0, $heightPt - 2.0 * self::PADDING_PT));
    }

    public static function textField(float $w, float $h, FieldSpec $spec, string $rawValue): string
    {
        $a = $spec->appearance;
        $multiline = $spec->isFlagSet(FieldFlag::MULTILINE);
        $size = self::resolveFontSize($a->fontSizePt, $h, $multiline);
        $encoded = Encoding::forFont($rawValue, $a->fontEncoding());

        $body = self::frame($w, $h, $a);
        $body .= "/Tx BMC\nq\n";
        $inset = max(0.0, $a->borderWidthPt) + 1.0;
        $body .= self::rect($inset, $inset, $w - 2.0 * $inset, $h - 2.0 * $inset) . " W n\n";

        if ($encoded !== '') {
            if ($spec->isComb() && $spec->maxLength !== null && $spec->maxLength > 0) {
                $body .= self::combText($w, $h, $a, $size, $encoded, $spec->maxLength);
            } else {
                $lines = $multiline ? explode("\n", $encoded) : [$encoded];
                $body .= self::textLines($w, $h, $a, $size, $lines, $multiline);
            }
        }

        return $body . "Q\nEMC";
    }

    public static function choice(float $w, float $h, FieldSpec $spec, string $rawValue): string
    {
        $a = $spec->appearance;
        $size = self::resolveFontSize($a->fontSizePt, $h, false);
        $encoded = Encoding::forFont($rawValue, $a->fontEncoding());

        $body = self::frame($w, $h, $a);
        $body .= "/Tx BMC\nq\n";
        $inset = max(0.0, $a->borderWidthPt) + 1.0;
        $body .= self::rect($inset, $inset, $w - 2.0 * $inset, $h - 2.0 * $inset) . " W n\n";
        if ($encoded !== '') {
            $body .= self::textLines($w, $h, $a, $size, [$encoded], false);
        }

        return $body . "Q\nEMC";
    }

    /** A `/Btn` checkbox / radio appearance state. $marker is 'check' or 'dot'. */
    public static function toggle(float $w, float $h, FieldSpec $spec, bool $on, string $marker): string
    {
        $a = $spec->appearance;
        $body = self::frame($w, $h, $a);
        if (!$on) {
            return rtrim($body, "\n");
        }

        $ink = $a->textColor;
        $body .= "q\n" . $ink->fillOp() . "\n" . $ink->strokeOp() . "\n";
        if ($marker === 'dot') {
            $cx = $w / 2.0;
            $cy = $h / 2.0;
            $r = min($w, $h) * 0.28;
            $k = 0.5523 * $r;
            $body .= self::n($cx + $r) . ' ' . self::n($cy) . " m\n";
            $body .= self::curve($cx + $r, $cy + $k, $cx + $k, $cy + $r, $cx, $cy + $r);
            $body .= self::curve($cx - $k, $cy + $r, $cx - $r, $cy + $k, $cx - $r, $cy);
            $body .= self::curve($cx - $r, $cy - $k, $cx - $k, $cy - $r, $cx, $cy - $r);
            $body .= self::curve($cx + $k, $cy - $r, $cx + $r, $cy - $k, $cx + $r, $cy);
            $body .= "f\n";
        } else {
            $lw = max(0.6, min($w, $h) * 0.12);
            $body .= self::n($lw) . " w\n";
            $body .= self::n($w * 0.22) . ' ' . self::n($h * 0.52) . " m\n";
            $body .= self::n($w * 0.42) . ' ' . self::n($h * 0.28) . " l\n";
            $body .= self::n($w * 0.80) . ' ' . self::n($h * 0.76) . " l\n";
            $body .= "S\n";
        }

        return $body . 'Q';
    }

    public static function pushButton(float $w, float $h, FieldSpec $spec): string
    {
        $a = $spec->appearance;
        $size = self::resolveFontSize($a->fontSizePt, $h, false);
        $label = Encoding::forFont($spec->buttonLabel ?? '', $a->fontEncoding());

        $body = self::frame($w, $h, $a);
        if ($label === '') {
            return rtrim($body, "\n");
        }

        $centred = new FieldAppearance(
            $a->font,
            $a->fontSizePt,
            $a->textColor,
            $a->borderColor,
            $a->backgroundColor,
            $a->borderWidthPt,
            1,
        );

        return $body . "q\n" . self::textLines($w, $h, $centred, $size, [$label], false) . 'Q';
    }

    public static function signature(float $w, float $h, FieldSpec $spec): string
    {
        return rtrim(self::frame($w, $h, $spec->appearance), "\n");
    }

    /** Background fill then an inset border stroke. */
    private static function frame(float $w, float $h, FieldAppearance $a): string
    {
        $out = '';
        if ($a->backgroundColor !== null) {
            $out .= "q\n" . $a->backgroundColor->fillOp() . "\n" . self::rect(0.0, 0.0, $w, $h) . " f\nQ\n";
        }
        if ($a->borderColor !== null && $a->borderWidthPt > 0.0) {
            $bw = $a->borderWidthPt;
            $out .= "q\n" . $a->borderColor->strokeOp() . "\n" . self::n($bw) . " w\n";
            $out .= self::rect($bw / 2.0, $bw / 2.0, $w - $bw, $h - $bw) . " S\nQ\n";
        }

        return $out;
    }

    /**
     * @param list<string> $lines already font-encoded
     */
    private static function textLines(
        float $w,
        float $h,
        FieldAppearance $a,
        float $size,
        array $lines,
        bool $multiline,
    ): string {
        $leading = $size * 1.15;
        $pad = max(0.0, $a->borderWidthPt) + self::PADDING_PT;
        $out = "BT\n/F" . $a->font->index . ' ' . self::n($size) . " Tf\n" . $a->textColor->fillOp() . "\n";
        $out .= self::n($leading) . " TL\n";

        $topBaseline = $multiline
            ? $h - $pad - $size
            : ($h - $size) / 2.0 + $size * 0.2;
        $topBaseline = max($pad, $topBaseline);

        $prevX = 0.0;
        $first = true;
        foreach ($lines as $line) {
            $x = self::alignX($w, self::advance($a, $line, $size), $a->quadding, $pad);
            if ($first) {
                $out .= self::n($x) . ' ' . self::n($topBaseline) . " Td\n";
                $first = false;
            } else {
                $out .= self::n($x - $prevX) . " 0 Td\nT*\n";
            }
            $out .= '(' . PdfString::escape($line) . ") Tj\n";
            $prevX = $x;
        }

        return $out . "ET\n";
    }

    private static function combText(
        float $w,
        float $h,
        FieldAppearance $a,
        float $size,
        string $encoded,
        int $cells,
    ): string {
        $cellW = $w / $cells;
        $baseline = max(0.0, ($h - $size) / 2.0 + $size * 0.2);
        $out = "BT\n/F" . $a->font->index . ' ' . self::n($size) . " Tf\n" . $a->textColor->fillOp() . "\n";
        $chars = str_split($encoded);
        $prevX = 0.0;
        $first = true;
        foreach ($chars as $i => $ch) {
            if ($i >= $cells) {
                break;
            }
            $x = max(0.0, $cellW * $i + ($cellW - self::advance($a, $ch, $size)) / 2.0);
            if ($first) {
                $out .= self::n($x) . ' ' . self::n($baseline) . " Td\n";
                $first = false;
            } else {
                $out .= self::n($x - $prevX) . " 0 Td\n";
            }
            $out .= '(' . PdfString::escape($ch) . ") Tj\n";
            $prevX = $x;
        }

        return $out . "ET\n";
    }

    private static function advance(FieldAppearance $a, string $encoded, float $size): float
    {
        return $a->font->metrics->stringWidth($encoded, $size);
    }

    private static function alignX(float $w, float $textWidth, int $quadding, float $pad): float
    {
        return match ($quadding) {
            1 => max($pad, ($w - $textWidth) / 2.0),
            2 => max($pad, $w - $pad - $textWidth),
            default => $pad,
        };
    }

    private static function rect(float $x, float $y, float $w, float $h): string
    {
        return self::n($x) . ' ' . self::n($y) . ' ' . self::n($w) . ' ' . self::n($h) . ' re';
    }

    private static function curve(float $x1, float $y1, float $x2, float $y2, float $x3, float $y3): string
    {
        return self::n($x1) . ' ' . self::n($y1) . ' ' . self::n($x2) . ' ' . self::n($y2)
            . ' ' . self::n($x3) . ' ' . self::n($y3) . " c\n";
    }

    private static function n(float $value): string
    {
        return FieldAppearance::num($value);
    }
}
