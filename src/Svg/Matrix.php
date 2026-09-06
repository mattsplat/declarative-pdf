<?php

declare(strict_types=1);

namespace Pdf\Svg;

use Pdf\Exception\SvgException;
use Pdf\Geometry\Point;

/**
 * The 2x3 affine transform of an SVG `transform` attribute, laid out as
 * `[a c e; b d f; 0 0 1]` — the same six numbers, in the same order, as both
 * SVG's `matrix()` and PDF's `cm`.
 *
 * {@see \Pdf\Geometry\PathCommand::transformed()} only scales and translates,
 * which cannot express `rotate` or `skew`. Rather than teach the geometry layer
 * about matrices, {@see SvgParser} composes transforms here and bakes them into
 * absolute coordinates, so nothing downstream ever sees one.
 */
final readonly class Matrix
{
    public function __construct(
        public float $a,
        public float $b,
        public float $c,
        public float $d,
        public float $e,
        public float $f,
    ) {
    }

    public static function identity(): self
    {
        return new self(1.0, 0.0, 0.0, 1.0, 0.0, 0.0);
    }

    public static function translate(float $tx, float $ty): self
    {
        return new self(1.0, 0.0, 0.0, 1.0, $tx, $ty);
    }

    public static function scaling(float $sx, float $sy): self
    {
        return new self($sx, 0.0, 0.0, $sy, 0.0, 0.0);
    }

    public static function rotation(float $degrees): self
    {
        $radians = deg2rad($degrees);
        $cos = cos($radians);
        $sin = sin($radians);

        return new self($cos, $sin, -$sin, $cos, 0.0, 0.0);
    }

    public static function skewX(float $degrees): self
    {
        return new self(1.0, 0.0, tan(deg2rad($degrees)), 1.0, 0.0, 0.0);
    }

    public static function skewY(float $degrees): self
    {
        return new self(1.0, tan(deg2rad($degrees)), 0.0, 1.0, 0.0, 0.0);
    }

    /**
     * Parse a `transform` attribute: a whitespace- or comma-separated list of
     * functions applied left to right, so the rightmost acts on the geometry
     * first.
     */
    public static function parse(string $transform): self
    {
        // `none` is a valid value meaning no transform at all.
        if (trim($transform) === '' || strtolower(trim($transform)) === 'none') {
            return self::identity();
        }

        $matched = preg_match_all(
            '/([a-zA-Z]+)\s*\(([^)]*)\)/',
            $transform,
            $matches,
            PREG_SET_ORDER,
        );
        if ($matched === false || $matched === 0) {
            throw new SvgException('Malformed SVG transform: ' . $transform);
        }

        $result = self::identity();
        foreach ($matches as $match) {
            $result = $result->multiply(self::fromFunction($match[1], self::numbers($match[2])));
        }

        return $result;
    }

    /** This transform applied after `$inner` — `$inner` acts on the point first. */
    public function multiply(self $inner): self
    {
        return new self(
            $this->a * $inner->a + $this->c * $inner->b,
            $this->b * $inner->a + $this->d * $inner->b,
            $this->a * $inner->c + $this->c * $inner->d,
            $this->b * $inner->c + $this->d * $inner->d,
            $this->a * $inner->e + $this->c * $inner->f + $this->e,
            $this->b * $inner->e + $this->d * $inner->f + $this->f,
        );
    }

    public function apply(Point $point): Point
    {
        return new Point(
            $this->a * $point->x + $this->c * $point->y + $this->e,
            $this->b * $point->x + $this->d * $point->y + $this->f,
        );
    }

    /**
     * The uniform scale this transform represents, as the square root of the
     * absolute determinant. A stroke is scaled by the transform along with the
     * geometry it outlines; under a non-uniform scale SVG draws an elliptical
     * pen, which a single PDF line width cannot express, so this is the usual
     * geometric-mean approximation.
     */
    public function meanScale(): float
    {
        return sqrt(abs($this->a * $this->d - $this->b * $this->c));
    }

    public function isIdentity(): bool
    {
        return $this->a === 1.0
            && $this->b === 0.0
            && $this->c === 0.0
            && $this->d === 1.0
            && $this->e === 0.0
            && $this->f === 0.0;
    }

    /** @param list<float> $args */
    private static function fromFunction(string $name, array $args): self
    {
        $count = count($args);

        return match (true) {
            $name === 'matrix' && $count === 6 => new self(
                $args[0],
                $args[1],
                $args[2],
                $args[3],
                $args[4],
                $args[5],
            ),
            $name === 'translate' && $count === 1 => self::translate($args[0], 0.0),
            $name === 'translate' && $count === 2 => self::translate($args[0], $args[1]),
            $name === 'scale' && $count === 1 => self::scaling($args[0], $args[0]),
            $name === 'scale' && $count === 2 => self::scaling($args[0], $args[1]),
            $name === 'rotate' && $count === 1 => self::rotation($args[0]),
            // rotate(a cx cy) is the rotation conjugated by a move to the centre.
            $name === 'rotate' && $count === 3 => self::translate($args[1], $args[2])
                ->multiply(self::rotation($args[0]))
                ->multiply(self::translate(-$args[1], -$args[2])),
            $name === 'skewX' && $count === 1 => self::skewX($args[0]),
            $name === 'skewY' && $count === 1 => self::skewY($args[0]),
            default => throw new SvgException(
                sprintf('Unsupported SVG transform: %s() with %d argument(s).', $name, $count),
            ),
        };
    }

    /** @return list<float> */
    private static function numbers(string $args): array
    {
        $found = preg_split('/[\s,]+/', trim($args), -1, PREG_SPLIT_NO_EMPTY);
        if ($found === false) {
            return [];
        }

        $numbers = [];
        foreach ($found as $token) {
            if (!is_numeric($token)) {
                throw new SvgException('Non-numeric SVG transform argument: ' . $token);
            }
            $numbers[] = (float) $token;
        }

        return $numbers;
    }
}
