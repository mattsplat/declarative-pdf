<?php

declare(strict_types=1);

namespace Pdf\Layout;

use Pdf\Exception\LayoutException;
use Pdf\Geometry\Rect;

/**
 * Splits a rectangle into sub-rectangles by weight or by fixed track, so a
 * sheet layout stops hand-computing `$x + $w + $gutter` chains.
 *
 * Every split returns child grids over their own sub-rectangle, so a band can
 * be split again — a lower row divided into columns, say:
 *
 *   [$top, $bottom] = $p->grid(gutterPt: 12)->rows(3, 1);
 *   [$left, $right] = $bottom->columns(1, 1);
 *   Panel::in($left->rect())->containing($blocks)->drawOn($p);
 *
 * {@see \Pdf\Builder\PageBuilder::grid()} starts one over a page's writable
 * area. Pure geometry, in points, top-down; it never touches the Y flip. The
 * gutter is carried into child grids — override it per level with
 * {@see self::gutter()}.
 */
final readonly class Grid
{
    private function __construct(
        private Rect $area,
        private float $gutterPt,
    ) {
        if ($gutterPt < 0.0) {
            throw new LayoutException('Grid gutter must not be negative.');
        }
    }

    public static function inside(Rect $area, float $gutterPt = 0.0): self
    {
        return new self($area, $gutterPt);
    }

    /** This (sub)grid's rectangle, in points. */
    public function rect(): Rect
    {
        return $this->area;
    }

    public function gutter(float $gutterPt): self
    {
        return new self($this->area, $gutterPt);
    }

    /**
     * Split into horizontal bands top-to-bottom by normalised weight.
     *
     * @return list<self>
     */
    public function rows(float ...$weights): array
    {
        return $this->slice(self::fractions($weights), vertical: true);
    }

    /**
     * Split into vertical bands left-to-right by normalised weight.
     *
     * @return list<self>
     */
    public function columns(float ...$weights): array
    {
        return $this->slice(self::fractions($weights), vertical: false);
    }

    /**
     * Split into horizontal bands using a mix of fixed ({@see Track::pt()}) and
     * fractional ({@see Track::fr()}) tracks.
     *
     * @param list<Track> $tracks
     * @return list<self>
     */
    public function rowTracks(array $tracks): array
    {
        return $this->slice($tracks, vertical: true);
    }

    /**
     * Split into vertical bands using a mix of fixed and fractional tracks.
     *
     * @param list<Track> $tracks
     * @return list<self>
     */
    public function columnTracks(array $tracks): array
    {
        return $this->slice($tracks, vertical: false);
    }

    /**
     * @param list<Track> $tracks
     * @return list<self>
     */
    private function slice(array $tracks, bool $vertical): array
    {
        if ($tracks === []) {
            throw new LayoutException('A grid split needs at least one track.');
        }

        $extent = $vertical ? $this->area->height : $this->area->width;
        $free = $extent - $this->gutterPt * (count($tracks) - 1);

        $fixedTotal = 0.0;
        $weightTotal = 0.0;
        foreach ($tracks as $track) {
            if ($track->isFraction) {
                $weightTotal += $track->value;
            } else {
                $fixedTotal += $track->value;
            }
        }

        $flexible = $free - $fixedTotal;
        if ($flexible < -1e-6) {
            throw new LayoutException(
                'Fixed tracks and gutters exceed the grid ' . ($vertical ? 'height' : 'width') . '.',
            );
        }
        $perWeight = $weightTotal > 0.0 ? $flexible / $weightTotal : 0.0;

        $slices = [];
        $offset = $vertical ? $this->area->y : $this->area->x;
        foreach ($tracks as $track) {
            $size = $track->isFraction ? $track->value * $perWeight : $track->value;
            $slices[] = new self($this->subRect($vertical, $offset, $size), $this->gutterPt);
            $offset += $size + $this->gutterPt;
        }

        return $slices;
    }

    private function subRect(bool $vertical, float $offset, float $size): Rect
    {
        return $vertical
            ? new Rect($this->area->x, $offset, $this->area->width, $size)
            : new Rect($offset, $this->area->y, $size, $this->area->height);
    }

    /**
     * @param list<float> $weights
     * @return list<Track>
     */
    private static function fractions(array $weights): array
    {
        return array_map(static fn (float $weight): Track => Track::fr($weight), $weights);
    }
}
