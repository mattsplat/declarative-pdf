<?php

declare(strict_types=1);

namespace Pdf\Svg;

use Pdf\Exception\SvgException;

/**
 * SVG length values, resolved to user units.
 *
 * One SVG user unit is one CSS pixel, and one CSS pixel is 3/4 of a point.
 * Invariant #1 says user units never reach the layout engine, so everything a
 * document says in `mm`, `pt` or `in` is normalised here and converted to
 * points exactly once, when {@see SvgParser} builds the root transform.
 *
 * Percentages are deliberately absent: they resolve against a viewport the
 * caller knows and this class does not, so {@see SvgParser} handles them.
 */
final class SvgLength
{
    /** CSS absolute units, in user units (px) each. */
    private const UNITS = [
        '' => 1.0,
        'px' => 1.0,
        'pt' => 96.0 / 72.0,
        'pc' => 16.0,
        'mm' => 96.0 / 25.4,
        'cm' => 96.0 / 2.54,
        'q' => 96.0 / 101.6,
        'in' => 96.0,
    ];

    /** There are 72 points to the inch and 96 user units. */
    public const POINTS_PER_USER_UNIT = 72.0 / 96.0;

    public static function userUnits(string $value): float
    {
        $value = trim($value);
        if ($value === '') {
            throw new SvgException('Empty SVG length.');
        }

        if (preg_match('/^([+-]?(?:\d*\.\d+|\d+\.?)(?:[eE][+-]?\d+)?)\s*([a-zA-Z%]*)$/', $value, $match) !== 1) {
            throw new SvgException('Malformed SVG length: ' . $value);
        }

        $unit = strtolower($match[2]);
        if (!isset(self::UNITS[$unit])) {
            throw new SvgException('Unsupported SVG length unit: ' . $value);
        }

        return (float) $match[1] * self::UNITS[$unit];
    }

    public static function points(string $value): float
    {
        return self::userUnits($value) * self::POINTS_PER_USER_UNIT;
    }

    /** True for a percentage, which only the viewport owner can resolve. */
    public static function isPercentage(string $value): bool
    {
        return str_ends_with(trim($value), '%');
    }
}
