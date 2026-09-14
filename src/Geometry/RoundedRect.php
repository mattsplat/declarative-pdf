<?php

declare(strict_types=1);

namespace Pdf\Geometry;

/**
 * The command list for a rectangle with (optionally per-corner) rounded
 * corners, clockwise from the top-left. A zero radius on a corner degenerates
 * to a plain right angle — the same quarter-circle Bézier approximation
 * {@see \Pdf\Node\Path::ellipse()} uses for a full circle.
 */
final class RoundedRect
{
    private const KAPPA = 0.5523;

    /**
     * @return list<PathCommand>
     */
    public static function commands(
        float $widthPt,
        float $heightPt,
        float $topLeftPt,
        float $topRightPt,
        float $bottomRightPt,
        float $bottomLeftPt,
    ): array {
        $tl = self::clamp($topLeftPt, $widthPt, $heightPt);
        $tr = self::clamp($topRightPt, $widthPt, $heightPt);
        $br = self::clamp($bottomRightPt, $widthPt, $heightPt);
        $bl = self::clamp($bottomLeftPt, $widthPt, $heightPt);

        $commands = [
            PathCommand::moveTo($tl, 0.0),
            PathCommand::lineTo($widthPt - $tr, 0.0),
        ];

        if ($tr > 0.0) {
            $o = $tr * self::KAPPA;
            $commands[] = PathCommand::curveTo($widthPt - $tr + $o, 0.0, $widthPt, $tr - $o, $widthPt, $tr);
        }
        $commands[] = PathCommand::lineTo($widthPt, $heightPt - $br);

        if ($br > 0.0) {
            $o = $br * self::KAPPA;
            $commands[] = PathCommand::curveTo($widthPt, $heightPt - $br + $o, $widthPt - $br + $o, $heightPt, $widthPt - $br, $heightPt);
        }
        $commands[] = PathCommand::lineTo($bl, $heightPt);

        if ($bl > 0.0) {
            $o = $bl * self::KAPPA;
            $commands[] = PathCommand::curveTo($bl - $o, $heightPt, 0.0, $heightPt - $bl + $o, 0.0, $heightPt - $bl);
        }
        $commands[] = PathCommand::lineTo(0.0, $tl);

        if ($tl > 0.0) {
            $o = $tl * self::KAPPA;
            $commands[] = PathCommand::curveTo(0.0, $tl - $o, $tl - $o, 0.0, $tl, 0.0);
        }
        $commands[] = PathCommand::close();

        return $commands;
    }

    private static function clamp(float $radiusPt, float $widthPt, float $heightPt): float
    {
        return max(0.0, min($radiusPt, $widthPt / 2, $heightPt / 2));
    }
}
