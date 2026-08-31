<?php

declare(strict_types=1);

namespace Pdf\Geometry;

/**
 * One side of a box. Used to place a single-edge accent border.
 */
enum Edge
{
    case Top;
    case Right;
    case Bottom;
    case Left;

    /** An {@see Edges} carrying `$widthPt` on this side and zero on the other three. */
    public function only(float $widthPt): Edges
    {
        return match ($this) {
            self::Top => new Edges(top: $widthPt),
            self::Right => new Edges(right: $widthPt),
            self::Bottom => new Edges(bottom: $widthPt),
            self::Left => new Edges(left: $widthPt),
        };
    }
}
