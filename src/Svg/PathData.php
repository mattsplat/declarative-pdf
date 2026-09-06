<?php

declare(strict_types=1);

namespace Pdf\Svg;

use Pdf\Exception\SvgException;
use Pdf\Geometry\PathCommand;

/**
 * The SVG path mini-language — the `d` attribute — as a list of
 * {@see PathCommand}s.
 *
 * PDF draws only lines and cubic Béziers, so three of SVG's operators are
 * converted on the way through: `H`/`V` become lines, `Q`/`T` are degree-
 * elevated to cubics, and `A` is approximated by up to four cubics per arc.
 * Relative operators, the `S`/`T` reflected control points and implicit
 * repeated coordinate pairs are all resolved here, so the result is a flat,
 * absolute list.
 *
 * Parsing is a character scanner rather than a tokeniser because arc flags are
 * single characters that need no separator: `a1 1 0 011 1` is a valid arc whose
 * fourth, fifth and sixth arguments are `0`, `1` and `1`. Splitting that on
 * numbers first would read `011` as one value.
 */
final class PathData
{
    /** Beyond a quarter turn a single cubic drifts visibly off the ellipse. */
    private const MAX_ARC_SEGMENT = M_PI / 2;

    private int $at = 0;

    private readonly int $length;

    private function __construct(private readonly string $d)
    {
        $this->length = strlen($d);
    }

    /** @return list<PathCommand> */
    public static function parse(string $d): array
    {
        return (new self($d))->run();
    }

    /** @return list<PathCommand> */
    private function run(): array
    {
        /** @var list<PathCommand> $commands */
        $commands = [];

        $x = 0.0;
        $y = 0.0;
        $startX = 0.0;
        $startY = 0.0;
        $cubicControlX = null;
        $cubicControlY = null;
        $quadControlX = null;
        $quadControlY = null;
        $previous = null;

        while (true) {
            $this->skipSeparators();
            if ($this->at >= $this->length) {
                break;
            }

            $character = $this->d[$this->at];
            if (ctype_alpha($character)) {
                $this->at++;
                $op = $character;
            } elseif ($previous !== null) {
                // A bare coordinate repeats the previous operator; after a
                // moveto the repeat is a lineto, per the grammar.
                $op = match ($previous) {
                    'M' => 'L',
                    'm' => 'l',
                    default => $previous,
                };
            } else {
                throw new SvgException('SVG path data must start with a command: ' . $this->d);
            }

            $relative = ctype_lower($op);
            $dx = $relative ? $x : 0.0;
            $dy = $relative ? $y : 0.0;

            switch (strtoupper($op)) {
                case 'M':
                    $x = $this->readNumber() + $dx;
                    $y = $this->readNumber() + $dy;
                    $commands[] = PathCommand::moveTo($x, $y);
                    $startX = $x;
                    $startY = $y;
                    $cubicControlX = $cubicControlY = $quadControlX = $quadControlY = null;
                    break;

                case 'L':
                    $x = $this->readNumber() + $dx;
                    $y = $this->readNumber() + $dy;
                    $commands[] = PathCommand::lineTo($x, $y);
                    $cubicControlX = $cubicControlY = $quadControlX = $quadControlY = null;
                    break;

                case 'H':
                    $x = $this->readNumber() + $dx;
                    $commands[] = PathCommand::lineTo($x, $y);
                    $cubicControlX = $cubicControlY = $quadControlX = $quadControlY = null;
                    break;

                case 'V':
                    $y = $this->readNumber() + $dy;
                    $commands[] = PathCommand::lineTo($x, $y);
                    $cubicControlX = $cubicControlY = $quadControlX = $quadControlY = null;
                    break;

                case 'C':
                    $c1x = $this->readNumber() + $dx;
                    $c1y = $this->readNumber() + $dy;
                    $c2x = $this->readNumber() + $dx;
                    $c2y = $this->readNumber() + $dy;
                    $x = $this->readNumber() + $dx;
                    $y = $this->readNumber() + $dy;
                    $commands[] = PathCommand::curveTo($c1x, $c1y, $c2x, $c2y, $x, $y);
                    $cubicControlX = $c2x;
                    $cubicControlY = $c2y;
                    $quadControlX = $quadControlY = null;
                    break;

                case 'S':
                    // The first control point mirrors the previous curve's
                    // second one; with no previous curve it collapses onto the
                    // current point.
                    $c1x = $cubicControlX !== null ? 2 * $x - $cubicControlX : $x;
                    $c1y = $cubicControlY !== null ? 2 * $y - $cubicControlY : $y;
                    $c2x = $this->readNumber() + $dx;
                    $c2y = $this->readNumber() + $dy;
                    $x = $this->readNumber() + $dx;
                    $y = $this->readNumber() + $dy;
                    $commands[] = PathCommand::curveTo($c1x, $c1y, $c2x, $c2y, $x, $y);
                    $cubicControlX = $c2x;
                    $cubicControlY = $c2y;
                    $quadControlX = $quadControlY = null;
                    break;

                case 'Q':
                    $qx = $this->readNumber() + $dx;
                    $qy = $this->readNumber() + $dy;
                    $endX = $this->readNumber() + $dx;
                    $endY = $this->readNumber() + $dy;
                    $commands[] = self::elevate($x, $y, $qx, $qy, $endX, $endY);
                    $x = $endX;
                    $y = $endY;
                    $quadControlX = $qx;
                    $quadControlY = $qy;
                    $cubicControlX = $cubicControlY = null;
                    break;

                case 'T':
                    $qx = $quadControlX !== null ? 2 * $x - $quadControlX : $x;
                    $qy = $quadControlY !== null ? 2 * $y - $quadControlY : $y;
                    $endX = $this->readNumber() + $dx;
                    $endY = $this->readNumber() + $dy;
                    $commands[] = self::elevate($x, $y, $qx, $qy, $endX, $endY);
                    $x = $endX;
                    $y = $endY;
                    $quadControlX = $qx;
                    $quadControlY = $qy;
                    $cubicControlX = $cubicControlY = null;
                    break;

                case 'A':
                    $rx = $this->readNumber();
                    $ry = $this->readNumber();
                    $rotation = $this->readNumber();
                    $largeArc = $this->readFlag();
                    $sweep = $this->readFlag();
                    $endX = $this->readNumber() + $dx;
                    $endY = $this->readNumber() + $dy;
                    foreach (self::arc($x, $y, $rx, $ry, $rotation, $largeArc, $sweep, $endX, $endY) as $segment) {
                        $commands[] = $segment;
                    }
                    $x = $endX;
                    $y = $endY;
                    $cubicControlX = $cubicControlY = $quadControlX = $quadControlY = null;
                    break;

                case 'Z':
                    $commands[] = PathCommand::close();
                    $x = $startX;
                    $y = $startY;
                    $cubicControlX = $cubicControlY = $quadControlX = $quadControlY = null;
                    // Z takes no arguments, so a following number would repeat
                    // it forever rather than being consumed.
                    if ($this->atNumber()) {
                        throw new SvgException('Unexpected coordinate after a closepath in: ' . $this->d);
                    }
                    break;

                default:
                    throw new SvgException('Unknown SVG path command: ' . $op);
            }

            $previous = $op;
        }

        return $commands;
    }

    /**
     * Raise a quadratic Bézier to the cubic PDF draws, which is exact: both
     * control points sit two thirds of the way from an endpoint to the
     * quadratic's single control point.
     */
    private static function elevate(
        float $x0,
        float $y0,
        float $qx,
        float $qy,
        float $x,
        float $y,
    ): PathCommand {
        return PathCommand::curveTo(
            $x0 + 2 / 3 * ($qx - $x0),
            $y0 + 2 / 3 * ($qy - $y0),
            $x + 2 / 3 * ($qx - $x),
            $y + 2 / 3 * ($qy - $y),
            $x,
            $y,
        );
    }

    /**
     * Endpoint-parameterised elliptical arc to cubics, following the
     * conversion in SVG 1.1 appendix F.6.
     *
     * @return list<PathCommand>
     */
    private static function arc(
        float $x0,
        float $y0,
        float $rx,
        float $ry,
        float $rotationDegrees,
        bool $largeArc,
        bool $sweep,
        float $x,
        float $y,
    ): array {
        // F.6.2: a zero-length arc is dropped, a zero radius degenerates to a line.
        if (abs($x - $x0) < 1e-12 && abs($y - $y0) < 1e-12) {
            return [];
        }

        $rx = abs($rx);
        $ry = abs($ry);
        if ($rx < 1e-12 || $ry < 1e-12) {
            return [PathCommand::lineTo($x, $y)];
        }

        $phi = deg2rad(fmod($rotationDegrees, 360.0));
        $cosPhi = cos($phi);
        $sinPhi = sin($phi);

        // F.6.5.1 — the endpoint midpoint in the ellipse's own frame.
        $halfDx = ($x0 - $x) / 2;
        $halfDy = ($y0 - $y) / 2;
        $x1p = $cosPhi * $halfDx + $sinPhi * $halfDy;
        $y1p = -$sinPhi * $halfDx + $cosPhi * $halfDy;

        // F.6.6 — grow radii that are too small to span the endpoints.
        $lambda = ($x1p ** 2) / ($rx ** 2) + ($y1p ** 2) / ($ry ** 2);
        if ($lambda > 1.0) {
            $scale = sqrt($lambda);
            $rx *= $scale;
            $ry *= $scale;
        }

        // F.6.5.2 — the centre, in the ellipse's frame.
        $numerator = $rx ** 2 * $ry ** 2 - $rx ** 2 * $y1p ** 2 - $ry ** 2 * $x1p ** 2;
        $denominator = $rx ** 2 * $y1p ** 2 + $ry ** 2 * $x1p ** 2;
        $factor = $denominator > 0.0 ? sqrt(max(0.0, $numerator) / $denominator) : 0.0;
        if ($largeArc === $sweep) {
            $factor = -$factor;
        }
        $cxp = $factor * $rx * $y1p / $ry;
        $cyp = -$factor * $ry * $x1p / $rx;

        // F.6.5.3 — back to user space.
        $cx = $cosPhi * $cxp - $sinPhi * $cyp + ($x0 + $x) / 2;
        $cy = $sinPhi * $cxp + $cosPhi * $cyp + ($y0 + $y) / 2;

        // F.6.5.5 / F.6.5.6 — the start angle and the angle swept.
        $theta = atan2(($y1p - $cyp) / $ry, ($x1p - $cxp) / $rx);
        $delta = atan2((-$y1p - $cyp) / $ry, (-$x1p - $cxp) / $rx) - $theta;
        if (!$sweep && $delta > 0.0) {
            $delta -= 2 * M_PI;
        } elseif ($sweep && $delta < 0.0) {
            $delta += 2 * M_PI;
        }

        $steps = max(1, (int) ceil(abs($delta) / self::MAX_ARC_SEGMENT));
        $step = $delta / $steps;
        // The tangent scaling that makes a cubic osculate the ellipse at both
        // ends; exact in the limit and within a thousandth of a point at 90°.
        $alpha = 4.0 / 3.0 * tan($step / 4);

        $segments = [];
        for ($index = 0; $index < $steps; $index++) {
            $t1 = $theta + $index * $step;
            $t2 = $t1 + $step;

            [$p1x, $p1y] = self::ellipsePoint($cx, $cy, $rx, $ry, $cosPhi, $sinPhi, $t1);
            [$d1x, $d1y] = self::ellipseTangent($rx, $ry, $cosPhi, $sinPhi, $t1);
            [$p2x, $p2y] = self::ellipsePoint($cx, $cy, $rx, $ry, $cosPhi, $sinPhi, $t2);
            [$d2x, $d2y] = self::ellipseTangent($rx, $ry, $cosPhi, $sinPhi, $t2);

            $segments[] = PathCommand::curveTo(
                $p1x + $alpha * $d1x,
                $p1y + $alpha * $d1y,
                $p2x - $alpha * $d2x,
                $p2y - $alpha * $d2y,
                $p2x,
                $p2y,
            );
        }

        return $segments;
    }

    /** @return array{0: float, 1: float} */
    private static function ellipsePoint(
        float $cx,
        float $cy,
        float $rx,
        float $ry,
        float $cosPhi,
        float $sinPhi,
        float $t,
    ): array {
        $cosT = cos($t);
        $sinT = sin($t);

        return [
            $cx + $rx * $cosT * $cosPhi - $ry * $sinT * $sinPhi,
            $cy + $rx * $cosT * $sinPhi + $ry * $sinT * $cosPhi,
        ];
    }

    /** @return array{0: float, 1: float} */
    private static function ellipseTangent(
        float $rx,
        float $ry,
        float $cosPhi,
        float $sinPhi,
        float $t,
    ): array {
        $cosT = cos($t);
        $sinT = sin($t);

        return [
            -$rx * $sinT * $cosPhi - $ry * $cosT * $sinPhi,
            -$rx * $sinT * $sinPhi + $ry * $cosT * $cosPhi,
        ];
    }

    private function skipSeparators(): void
    {
        while ($this->at < $this->length) {
            $character = $this->d[$this->at];
            if ($character === ' ' || $character === ',' || $character === "\n"
                || $character === "\r" || $character === "\t" || $character === "\f"
            ) {
                $this->at++;
                continue;
            }

            break;
        }
    }

    private function readNumber(): float
    {
        $this->skipSeparators();
        $start = $this->at;

        if ($this->at < $this->length && ($this->d[$this->at] === '-' || $this->d[$this->at] === '+')) {
            $this->at++;
        }
        while ($this->at < $this->length && ctype_digit($this->d[$this->at])) {
            $this->at++;
        }
        if ($this->at < $this->length && $this->d[$this->at] === '.') {
            $this->at++;
            while ($this->at < $this->length && ctype_digit($this->d[$this->at])) {
                $this->at++;
            }
        }
        if ($this->at < $this->length && ($this->d[$this->at] === 'e' || $this->d[$this->at] === 'E')) {
            // Only consume the exponent if it is well-formed; `10e` is a number
            // followed by a stray command letter, not a broken float.
            $mark = $this->at;
            $this->at++;
            if ($this->at < $this->length && ($this->d[$this->at] === '-' || $this->d[$this->at] === '+')) {
                $this->at++;
            }
            if ($this->at < $this->length && ctype_digit($this->d[$this->at])) {
                while ($this->at < $this->length && ctype_digit($this->d[$this->at])) {
                    $this->at++;
                }
            } else {
                $this->at = $mark;
            }
        }

        $text = substr($this->d, $start, $this->at - $start);
        if (!is_numeric($text)) {
            throw new SvgException(
                sprintf('Expected a number at offset %d of SVG path data: %s', $start, $this->d),
            );
        }

        return (float) $text;
    }

    /**
     * An arc flag: exactly one `0` or `1`, with no separator needed before the
     * next argument.
     */
    private function readFlag(): bool
    {
        $this->skipSeparators();
        if ($this->at >= $this->length) {
            throw new SvgException('Truncated arc in SVG path data: ' . $this->d);
        }

        $character = $this->d[$this->at];
        if ($character !== '0' && $character !== '1') {
            throw new SvgException(
                sprintf('Expected an arc flag at offset %d of SVG path data: %s', $this->at, $this->d),
            );
        }
        $this->at++;

        return $character === '1';
    }

    private function atNumber(): bool
    {
        $this->skipSeparators();
        if ($this->at >= $this->length) {
            return false;
        }

        $character = $this->d[$this->at];

        return ctype_digit($character) || $character === '-' || $character === '+' || $character === '.';
    }
}
