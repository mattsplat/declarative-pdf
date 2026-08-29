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
 * The constructor is points-only; the static factories take user units.
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
            $scaled[] = $command->scaled($scale);
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
        return self::of(
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

        return self::of(
            [
                PathCommand::moveTo($cx + $cx, $cy),
                PathCommand::curveTo($cx + $cx, $cy + $oy, $cx + $ox, $cy + $cy, $cx, $cy + $cy),
                PathCommand::curveTo($cx - $ox, $cy + $cy, $cx - $cx, $cy + $oy, $cx - $cx, $cy),
                PathCommand::curveTo($cx - $cx, $cy - $oy, $cx - $ox, $cy - $cy, $cx, $cy - $cy),
                PathCommand::curveTo($cx + $ox, $cy - $cy, $cx + $cx, $cy - $oy, $cx + $cx, $cy),
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

        return self::of($commands, max($width, $minimum), max($height, $minimum), $paint, $unit, $patch);
    }
}
