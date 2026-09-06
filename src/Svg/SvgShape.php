<?php

declare(strict_types=1);

namespace Pdf\Svg;

use Pdf\Geometry\PathCommand;
use Pdf\Style\Paint;

/**
 * One painted figure from an SVG: absolute linework plus the {@see Paint} that
 * marks it.
 *
 * An SVG is a list of these because every element carries its own paint, which
 * a single {@see \Pdf\Node\Path} cannot express.
 */
final readonly class SvgShape
{
    /** @param list<PathCommand> $commands coordinates in points, relative to the SVG's top-left */
    public function __construct(
        public array $commands,
        public Paint $paint,
    ) {
    }

    /**
     * The same figure scaled. The stroke scales with the geometry it outlines,
     * exactly as it would under an SVG `transform`; under a non-uniform scale
     * it takes the geometric mean, since one PDF line width cannot describe
     * the elliptical pen SVG would use.
     */
    public function scaled(float $scaleX, float $scaleY): self
    {
        if ($scaleX === 1.0 && $scaleY === 1.0) {
            return $this;
        }

        $commands = [];
        foreach ($this->commands as $command) {
            $commands[] = $command->transformed($scaleX, $scaleY);
        }

        $strokeWidthPt = $this->paint->strokeWidthPt * sqrt(abs($scaleX * $scaleY));

        return new self($commands, $this->paint->withStrokeWidthPt($strokeWidthPt));
    }
}
