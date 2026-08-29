<?php

declare(strict_types=1);

namespace Pdf\Node;

use Pdf\Geometry\PathCommand;
use Pdf\Geometry\Point;
use Pdf\Geometry\Unit;
use Pdf\Style\Paint;
use Pdf\Style\StylePatch;

/**
 * Vector linework: an ordered list of {@see PathCommand}s painted with a solid
 * {@see Paint}.
 *
 * Coordinates are relative to the path's own box, top-left origin, y increasing
 * downward — the same convention as every other node. The box does *not*
 * shrink-wrap the geometry: the author states `$widthPt` / `$heightPt`, which
 * is what the path occupies in block flow and what a `place()` area scales.
 * Anything drawn outside those bounds still marks the page.
 *
 * The constructor is points-only and takes your commands verbatim; the static
 * factories take user units and inset the geometry they generate by half the
 * stroke width, so a stroked shape's ink stays inside the declared box.
 */
final readonly class Path implements BlockNode
{
    /** The Bézier control-point ratio that approximates a quarter circle. */
    private const KAPPA = 0.5523;

    /** @var list<PathCommand> */
    public array $commands;

    /** @param iterable<PathCommand> $commands */
    public function __construct(
        iterable $commands,
        public float $widthPt,
        public float $heightPt,
        public Paint $paint = new Paint(),
        private StylePatch $patch = new StylePatch(),
    ) {
        $this->commands = is_array($commands) ? array_values($commands) : iterator_to_array($commands, false);
    }

    /**
     * An arbitrary command list sized in `$unit`.
     *
     * @param iterable<PathCommand> $commands
     */
    public static function of(
        iterable $commands,
        float $width,
        float $height,
        Paint $paint = new Paint(),
        Unit $unit = Unit::Mm,
        StylePatch $patch = new StylePatch(),
    ): self {
        $scale = $unit->pointsPerUnit();
        $scaled = [];
        foreach ($commands as $command) {
            $scaled[] = $command->transformed($scale, $scale);
        }

        return new self($scaled, $width * $scale, $height * $scale, $paint, $patch);
    }

    /** A single straight segment. The box is the segment's bounding extent. */
    public static function line(
        float $x1,
        float $y1,
        float $x2,
        float $y2,
        Paint $paint = new Paint(),
        Unit $unit = Unit::Mm,
        StylePatch $patch = new StylePatch(),
    ): self {
        return self::bounded(
            [PathCommand::moveTo($x1, $y1), PathCommand::lineTo($x2, $y2)],
            [new Point($x1, $y1), new Point($x2, $y2)],
            $paint,
            $unit,
            $patch,
        );
    }

    /** An axis-aligned rectangle filling the whole box. */
    public static function rectangle(
        float $width,
        float $height,
        Paint $paint = new Paint(),
        Unit $unit = Unit::Mm,
        StylePatch $patch = new StylePatch(),
    ): self {
        return self::generated(
            [
                PathCommand::moveTo(0.0, 0.0),
                PathCommand::lineTo($width, 0.0),
                PathCommand::lineTo($width, $height),
                PathCommand::lineTo(0.0, $height),
                PathCommand::close(),
            ],
            $width,
            $height,
            $paint,
            $unit,
            $patch,
        );
    }

    /** An ellipse inscribed in the box, as the usual four-Bézier approximation. */
    public static function ellipse(
        float $width,
        float $height,
        Paint $paint = new Paint(),
        Unit $unit = Unit::Mm,
        StylePatch $patch = new StylePatch(),
    ): self {
        $cx = $width / 2;
        $cy = $height / 2;
        $ox = $cx * self::KAPPA;
        $oy = $cy * self::KAPPA;

        return self::generated(
            [
                PathCommand::moveTo($width, $cy),
                PathCommand::curveTo($width, $cy + $oy, $cx + $ox, $height, $cx, $height),
                PathCommand::curveTo($cx - $ox, $height, 0.0, $cy + $oy, 0.0, $cy),
                PathCommand::curveTo(0.0, $cy - $oy, $cx - $ox, 0.0, $cx, 0.0),
                PathCommand::curveTo($cx + $ox, 0.0, $width, $cy - $oy, $width, $cy),
                PathCommand::close(),
            ],
            $width,
            $height,
            $paint,
            $unit,
            $patch,
        );
    }

    /**
     * A closed polygon through `$points`. The box is their bounding extent.
     *
     * @param list<Point> $points
     */
    public static function polygon(
        array $points,
        Paint $paint = new Paint(),
        Unit $unit = Unit::Mm,
        StylePatch $patch = new StylePatch(),
    ): self {
        $commands = [];
        foreach ($points as $index => $point) {
            $commands[] = $index === 0
                ? PathCommand::moveTo($point->x, $point->y)
                : PathCommand::lineTo($point->x, $point->y);
        }
        if ($commands !== []) {
            $commands[] = PathCommand::close();
        }

        return self::bounded($commands, $points, $paint, $unit, $patch);
    }

    public function patch(): StylePatch
    {
        return $this->patch;
    }

    /**
     * Size the box to the extent of `$points` from the origin, never narrower
     * than the stroke so a flat figure still reserves its own ink in flow.
     *
     * @param list<PathCommand> $commands
     * @param list<Point> $points
     */
    private static function bounded(
        array $commands,
        array $points,
        Paint $paint,
        Unit $unit,
        StylePatch $patch,
    ): self {
        $width = 0.0;
        $height = 0.0;
        foreach ($points as $point) {
            $width = max($width, $point->x);
            $height = max($height, $point->y);
        }

        $minimum = $paint->strokes() ? $unit->fromPoints($paint->strokeWidthPt) : 0.0;

        return self::generated($commands, max($width, $minimum), max($height, $minimum), $paint, $unit, $patch);
    }

    /**
     * Fit geometry drawn across the full `$width` x `$height` box into the box
     * *including its ink*: a stroke straddles the line it is drawn on, so
     * generated figures are inset by half the stroke width on every side.
     * Without it a stroked shape bleeds into the neighbouring node's spacing,
     * or off the top of the page into the margin. A fill reaches the edge
     * exactly and is left alone.
     *
     * @param list<PathCommand> $commands drawn in the un-inset box
     */
    private static function generated(
        array $commands,
        float $width,
        float $height,
        Paint $paint,
        Unit $unit,
        StylePatch $patch,
    ): self {
        if (!$paint->strokes()) {
            return self::of($commands, $width, $height, $paint, $unit, $patch);
        }

        // Clamped so a stroke wider than the box collapses to its centre line
        // rather than turning the geometry inside out.
        $inset = $unit->fromPoints($paint->strokeWidthPt / 2);
        $insetX = min($inset, $width / 2);
        $insetY = min($inset, $height / 2);
        $scaleX = $width > 0.0 ? ($width - 2 * $insetX) / $width : 0.0;
        $scaleY = $height > 0.0 ? ($height - 2 * $insetY) / $height : 0.0;

        $fitted = [];
        foreach ($commands as $command) {
            $fitted[] = $command->transformed($scaleX, $scaleY, $insetX, $insetY);
        }

        return self::of($fitted, $width, $height, $paint, $unit, $patch);
    }
}
