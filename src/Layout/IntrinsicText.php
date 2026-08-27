<?php

declare(strict_types=1);

namespace Pdf\Layout;

/**
 * Minimum and maximum content width of a styled text sequence, in points.
 *
 *  - min: the widest unbreakable token (whitespace/newline delimited), so the
 *    text can be laid out at this width without a word overflowing.
 *  - max: the widest hard line (segments between explicit newlines) laid out
 *    without wrapping.
 *
 * Feeds table column autosizing; FPDF had no equivalent (`GetStringWidth` is
 * the only reused piece).
 */
final class IntrinsicText
{
    /**
     * @param list<ResolvedRun> $runs
     * @return array{0: float, 1: float} [min, max]
     */
    public static function measure(array $runs): array
    {
        $minWidth = 0.0;
        $maxWidth = 0.0;

        $tokenWidth = 0.0;
        $lineWidth = 0.0;

        foreach ($runs as $run) {
            if ($run->isImage()) {
                // An inline image is an unbreakable token.
                $tokenWidth += $run->imageWidthPt;
                $lineWidth += $run->imageWidthPt;
                $minWidth = max($minWidth, $tokenWidth);
                continue;
            }

            $length = strlen($run->text);
            for ($i = 0; $i < $length; $i++) {
                $byte = $run->text[$i];

                if ($byte === "\n") {
                    $minWidth = max($minWidth, $tokenWidth);
                    $maxWidth = max($maxWidth, $lineWidth);
                    $tokenWidth = 0.0;
                    $lineWidth = 0.0;
                    continue;
                }

                $advance = $run->font->metrics->charAdvance($byte) * $run->fontSizePt / 1000.0;
                $lineWidth += $advance;

                if ($byte === ' ') {
                    $minWidth = max($minWidth, $tokenWidth);
                    $tokenWidth = 0.0;
                } else {
                    $tokenWidth += $advance;
                }
            }
        }

        $minWidth = max($minWidth, $tokenWidth);
        $maxWidth = max($maxWidth, $lineWidth);

        return [$minWidth, $maxWidth];
    }
}
