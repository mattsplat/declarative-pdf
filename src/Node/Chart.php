<?php

declare(strict_types=1);

namespace Pdf\Node;

use Pdf\Chart\ChartKind;
use Pdf\Chart\LegendPosition;
use Pdf\Chart\Series;
use Pdf\Color\Color;
use Pdf\Exception\PdfException;
use Pdf\Geometry\Unit;
use Pdf\Style\StylePatch;

/**
 * A thin data chart drawn with the vector {@see Path} primitive and text — bar,
 * line, pie or sparkline. It occupies a fixed `$widthPt` x `$heightPt` box in
 * block flow (or in a `place()` area) exactly like a {@see Path}: it never
 * splits, and it moves whole to the next page when it does not fit.
 *
 * The constructor is points-only. The static factories — {@see self::bar()},
 * {@see self::line()}, {@see self::pie()}, {@see self::sparkline()} — take the
 * caller's {@see Unit} and are the intended entry points. Series colours left
 * null are filled from {@see \Pdf\Chart\Palette} by position at render time, so
 * output stays deterministic without the caller choosing a palette.
 */
final readonly class Chart implements BlockNode
{
    /** @var list<Series> */
    public array $series;

    /** @var list<string> */
    public array $categories;

    /**
     * @param iterable<Series> $series
     * @param iterable<string> $categories
     */
    public function __construct(
        public ChartKind $kind,
        iterable $series,
        public float $widthPt,
        public float $heightPt,
        iterable $categories = [],
        public LegendPosition $legend = LegendPosition::None,
        public bool $axes = true,
        private StylePatch $patch = new StylePatch(),
    ) {
        $this->series = is_array($series) ? array_values($series) : iterator_to_array($series, false);
        $this->categories = is_array($categories) ? array_values($categories) : iterator_to_array($categories, false);

        if ($this->series === []) {
            throw new PdfException('A chart needs at least one series.');
        }
        if ($this->widthPt <= 0.0 || $this->heightPt <= 0.0) {
            throw new PdfException('A chart needs a positive width and height.');
        }
    }

    public function patch(): StylePatch
    {
        return $this->patch;
    }

    /**
     * @param iterable<Series> $series
     * @param iterable<string> $categories
     */
    public static function bar(
        iterable $series,
        iterable $categories = [],
        float $width = 120.0,
        float $height = 70.0,
        Unit $unit = Unit::Mm,
        LegendPosition $legend = LegendPosition::None,
        StylePatch $patch = new StylePatch(),
    ): self {
        return new self(
            ChartKind::Bar,
            $series,
            $unit->toPoints($width),
            $unit->toPoints($height),
            $categories,
            $legend,
            true,
            $patch,
        );
    }

    /**
     * @param iterable<Series> $series
     * @param iterable<string> $categories
     */
    public static function line(
        iterable $series,
        iterable $categories = [],
        float $width = 120.0,
        float $height = 70.0,
        Unit $unit = Unit::Mm,
        LegendPosition $legend = LegendPosition::None,
        StylePatch $patch = new StylePatch(),
    ): self {
        return new self(
            ChartKind::Line,
            $series,
            $unit->toPoints($width),
            $unit->toPoints($height),
            $categories,
            $legend,
            true,
            $patch,
        );
    }

    /**
     * A single ring of slices sized by `|value|`.
     *
     * @param iterable<float|int> $values
     * @param iterable<string> $labels one per slice, used for the legend
     */
    public static function pie(
        iterable $values,
        iterable $labels = [],
        float $size = 70.0,
        Unit $unit = Unit::Mm,
        LegendPosition $legend = LegendPosition::Right,
        StylePatch $patch = new StylePatch(),
    ): self {
        $side = $unit->toPoints($size);

        return new self(
            ChartKind::Pie,
            [new Series('', $values)],
            $side,
            $side,
            $labels,
            $legend,
            false,
            $patch,
        );
    }

    /**
     * A bare trend line: no axes, no labels, no legend. Sized in points by
     * default so it drops inline next to a table cell or a KPI figure.
     *
     * @param iterable<float|int> $values
     */
    public static function sparkline(
        iterable $values,
        float $width = 120.0,
        float $height = 22.0,
        ?Color $color = null,
        Unit $unit = Unit::Pt,
        StylePatch $patch = new StylePatch(),
    ): self {
        return new self(
            ChartKind::Sparkline,
            [new Series('', $values, $color)],
            $unit->toPoints($width),
            $unit->toPoints($height),
            [],
            LegendPosition::None,
            false,
            $patch,
        );
    }
}
