<?php

declare(strict_types=1);

namespace Pdf\Geometry;

/**
 * Nine-point alignment of content within a rectangle.
 */
enum BoxAlign
{
    case TopLeft;
    case TopCenter;
    case TopRight;
    case CenterLeft;
    case Center;
    case CenterRight;
    case BottomLeft;
    case BottomCenter;
    case BottomRight;

    /** 0.0 = left, 0.5 = centre, 1.0 = right. */
    public function horizontalFraction(): float
    {
        return match ($this) {
            self::TopLeft, self::CenterLeft, self::BottomLeft => 0.0,
            self::TopCenter, self::Center, self::BottomCenter => 0.5,
            self::TopRight, self::CenterRight, self::BottomRight => 1.0,
        };
    }

    /** 0.0 = top, 0.5 = middle, 1.0 = bottom (from-top coordinates). */
    public function verticalFraction(): float
    {
        return match ($this) {
            self::TopLeft, self::TopCenter, self::TopRight => 0.0,
            self::CenterLeft, self::Center, self::CenterRight => 0.5,
            self::BottomLeft, self::BottomCenter, self::BottomRight => 1.0,
        };
    }
}
