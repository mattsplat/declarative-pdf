<?php

declare(strict_types=1);

namespace Pdf\Style;

use Pdf\Color\Color;
use Pdf\Exception\PdfException;

/**
 * A colour gradient used as a {@see Path} fill, realised as a PDF `/Shading`
 * (axial for {@see LinearGradient}, radial for {@see RadialGradient}) painted
 * with the `sh` operator inside the path clip.
 *
 * Stop offsets and the geometric parameters of each subclass are fractions of
 * the path's own box (0 = left / top, 1 = right / bottom); the renderer
 * resolves them to points and flips the Y axis in the one place it is allowed
 * to. Stops are normalised so the list always starts at offset 0 and ends at
 * offset 1.
 */
abstract readonly class Gradient
{
    /** @var list<GradientStop> */
    public array $stops;

    /** @param iterable<GradientStop> $stops */
    protected function __construct(iterable $stops, public GradientSpread $spread)
    {
        $list = is_array($stops) ? array_values($stops) : iterator_to_array($stops, false);
        if (count($list) < 2) {
            throw new PdfException('A gradient needs at least two colour stops.');
        }

        $previous = null;
        foreach ($list as $stop) {
            if ($previous !== null && $stop->offset < $previous) {
                throw new PdfException('Gradient stop offsets must not decrease.');
            }
            $previous = $stop->offset;
        }

        if ($list[0]->offset > 0.0) {
            array_unshift($list, new GradientStop(0.0, $list[0]->color));
        }
        $last = $list[count($list) - 1];
        if ($last->offset < 1.0) {
            $list[] = new GradientStop(1.0, $last->color);
        }

        $this->stops = $list;
    }

    /** The `/ShadingType`: 2 for axial, 3 for radial. */
    abstract public function shadingType(): int;

    /**
     * The shading's `/Coords` array, already in final page space.
     *
     * @param \Closure(float, float): array{0: float, 1: float} $place
     *   maps a box-relative point (points, top-left origin) to page space
     * @return list<float>
     */
    abstract public function coords(\Closure $place, float $boxWidthPt, float $boxHeightPt): array;

    /**
     * The stop colours as a PDF interpolation function: a single exponential
     * function for two stops, otherwise a stitching function over one
     * exponential segment per adjacent pair.
     */
    public function functionDictionary(): string
    {
        $stops = $this->stops;
        if (count($stops) === 2) {
            return self::segment($stops[0]->color, $stops[1]->color);
        }

        $segments = [];
        $bounds = [];
        $encode = [];
        for ($i = 0, $n = count($stops) - 1; $i < $n; $i++) {
            $segments[] = self::segment($stops[$i]->color, $stops[$i + 1]->color);
            $encode[] = '0 1';
            if ($i > 0) {
                $bounds[] = sprintf('%.5F', $stops[$i]->offset);
            }
        }

        return sprintf(
            '<</FunctionType 3 /Domain [0 1] /Functions [%s] /Bounds [%s] /Encode [%s]>>',
            implode(' ', $segments),
            implode(' ', $bounds),
            implode(' ', $encode),
        );
    }

    private static function segment(Color $from, Color $to): string
    {
        return sprintf(
            '<</FunctionType 2 /Domain [0 1] /C0 [%s] /C1 [%s] /N 1>>',
            self::components($from),
            self::components($to),
        );
    }

    private static function components(Color $color): string
    {
        return sprintf('%.4F %.4F %.4F', $color->r / 255, $color->g / 255, $color->b / 255);
    }
}
