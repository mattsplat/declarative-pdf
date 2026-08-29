<?php

declare(strict_types=1);

namespace Pdf\Geometry;

/**
 * One segment of a {@see \Pdf\Node\Path}: an operator plus its operand points,
 * in the path's own top-left coordinate space.
 *
 * A `MoveTo` starts a new subpath, so a single flat list of commands describes
 * an arbitrary multi-subpath figure.
 */
final readonly class PathCommand
{
    /** @var list<Point> */
    public array $points;

    /** @param list<Point> $points */
    private function __construct(public PathOp $op, array $points)
    {
        $this->points = $points;
    }

    public static function moveTo(float $x, float $y): self
    {
        return new self(PathOp::MoveTo, [new Point($x, $y)]);
    }

    public static function lineTo(float $x, float $y): self
    {
        return new self(PathOp::LineTo, [new Point($x, $y)]);
    }

    /** A cubic Bézier from the current point via two control points. */
    public static function curveTo(
        float $c1x,
        float $c1y,
        float $c2x,
        float $c2y,
        float $x,
        float $y,
    ): self {
        return new self(PathOp::CurveTo, [new Point($c1x, $c1y), new Point($c2x, $c2y), new Point($x, $y)]);
    }

    public static function close(): self
    {
        return new self(PathOp::Close, []);
    }

    /** Multiply every operand — how a user unit is converted to points. */
    public function scaled(float $factor): self
    {
        if ($factor === 1.0 || $this->points === []) {
            return $this;
        }

        return new self(
            $this->op,
            array_map(static fn (Point $p): Point => new Point($p->x * $factor, $p->y * $factor), $this->points),
        );
    }
}
