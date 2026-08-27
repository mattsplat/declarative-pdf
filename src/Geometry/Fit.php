<?php

declare(strict_types=1);

namespace Pdf\Geometry;

/**
 * How placed content (an image or an imported PDF page) is scaled into its
 * area rectangle.
 */
enum Fit
{
    /** Scale to fit inside the rect, preserving aspect ratio. */
    case Contain;
    /** Scale to cover the rect, preserving aspect ratio; overflow is clipped. */
    case Cover;
    /** Scale each axis independently to exactly fill the rect (distorts). */
    case Stretch;
    /** No scaling; align within the rect; overflow is clipped. */
    case ActualSize;
    /** Scale so the width matches the rect; height follows the aspect ratio. */
    case FitWidth;
    /** Scale so the height matches the rect; width follows the aspect ratio. */
    case FitHeight;

    /**
     * Resolve to (scaleX, scaleY) for a source of size ($sw, $sh) in a rect of
     * ($rw, $rh).
     *
     * @return array{0: float, 1: float}
     */
    public function scale(float $sw, float $sh, float $rw, float $rh): array
    {
        if ($sw <= 0.0 || $sh <= 0.0) {
            return [1.0, 1.0];
        }

        return match ($this) {
            self::Contain => self::uniform(min($rw / $sw, $rh / $sh)),
            self::Cover => self::uniform(max($rw / $sw, $rh / $sh)),
            self::Stretch => [$rw / $sw, $rh / $sh],
            self::ActualSize => [1.0, 1.0],
            self::FitWidth => self::uniform($rw / $sw),
            self::FitHeight => self::uniform($rh / $sh),
        };
    }

    /** True when content may exceed the rect and needs a clip. */
    public function clips(): bool
    {
        return $this === self::Cover || $this === self::ActualSize;
    }

    /** @return array{0: float, 1: float} */
    private static function uniform(float $s): array
    {
        return [$s, $s];
    }
}
