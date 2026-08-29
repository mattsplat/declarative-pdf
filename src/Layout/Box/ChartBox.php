<?php

declare(strict_types=1);

namespace Pdf\Layout\Box;

use Pdf\Chart\ChartKind;
use Pdf\Chart\LegendPosition;
use Pdf\Chart\Palette;
use Pdf\Chart\Plot;
use Pdf\Chart\Scale;
use Pdf\Chart\Series;
use Pdf\Color\Color;
use Pdf\Font\ResolvedFont;
use Pdf\Geometry\PathCommand;
use Pdf\Geometry\Point;
use Pdf\Layout\Canvas;
use Pdf\Style\Paint;
use Pdf\Text\Encoding;

/**
 * Renders a {@see \Pdf\Node\Chart} as vector linework and text through the
 * {@see Canvas}. Fixed size: like {@see PathBox} it never splits and moves
 * whole to the next page when it does not fit.
 *
 * All geometry is computed relative to the box's top-left corner and handed to
 * the canvas translated by the render origin, so nothing in here does the Y
 * flip — {@see \Pdf\Render\ContentStream} still owns it.
 */
final class ChartBox extends AbstractBox
{
    private const TICK_LENGTH_PT = 3.0;
    private const AXIS_GAP_PT = 3.0;
    private const LEGEND_GAP_PT = 12.0;
    private const LEGEND_PAD_PT = 8.0;

    /**
     * @param list<Series> $series
     * @param list<string> $categories
     */
    public function __construct(
        private readonly ChartKind $kind,
        private readonly array $series,
        private readonly array $categories,
        private readonly float $widthPt,
        private readonly float $heightPt,
        private readonly LegendPosition $legend,
        private readonly bool $axes,
        private readonly ResolvedFont $font,
        private readonly float $labelSizePt,
        private readonly Color $textColor,
        private readonly float $marginBeforePt,
        private readonly float $marginAfterPt,
    ) {
    }

    public function contentHeightPt(): float
    {
        return $this->heightPt;
    }

    public function marginBeforePt(): float
    {
        return $this->marginBeforePt;
    }

    public function marginAfterPt(): float
    {
        return $this->marginAfterPt;
    }

    public function minIntrinsicWidthPt(): float
    {
        return $this->widthPt;
    }

    public function maxIntrinsicWidthPt(): float
    {
        return $this->widthPt;
    }

    public function split(float $availableHeightPt): array
    {
        return $this->heightPt <= $availableHeightPt + 1e-4 ? [$this, null] : [null, $this];
    }

    public function render(Canvas $canvas, float $xPt, float $yTopPt, float $widthPt): void
    {
        // Plot area shrinks to leave room for the legend band, then for axes.
        [$padTop, $padRight, $padBottom, $padLeft] = $this->legendInsets();

        $plotLeft = $padLeft;
        $plotTop = $padTop;
        $plotWidth = max(0.0, $this->widthPt - $padLeft - $padRight);
        $plotHeight = max(0.0, $this->heightPt - $padTop - $padBottom);

        match ($this->kind) {
            ChartKind::Bar, ChartKind::Line => $this->renderCartesian(
                $canvas,
                $xPt,
                $yTopPt,
                $plotLeft,
                $plotTop,
                $plotWidth,
                $plotHeight,
            ),
            ChartKind::Pie => $this->renderPie($canvas, $xPt, $yTopPt, $plotLeft, $plotTop, $plotWidth, $plotHeight),
            ChartKind::Sparkline => $this->renderSparkline($canvas, $xPt, $yTopPt),
        };

        $this->renderLegend($canvas, $xPt, $yTopPt, $plotLeft, $plotTop, $plotWidth, $plotHeight);
    }

    private function renderCartesian(
        Canvas $canvas,
        float $ox,
        float $oy,
        float $plotLeft,
        float $plotTop,
        float $plotWidth,
        float $plotHeight,
    ): void {
        $slots = $this->slotCount();
        [$dataMin, $dataMax] = $this->dataRange();

        // Bars are read against zero, so the axis must include it; a line is
        // read by slope, so it fits the data and uses the vertical space.
        $includeZero = $this->kind === ChartKind::Bar;
        $scale = Scale::nice(
            $includeZero ? min(0.0, $dataMin) : $dataMin,
            $includeZero ? max(0.0, $dataMax) : $dataMax,
        );

        $labelGutter = $this->axes ? $this->valueLabelGutter($scale) : 0.0;
        $catGutter = $this->axes && $this->categories !== [] ? $this->labelSizePt * 1.4 : 0.0;

        $px = $plotLeft + $labelGutter;
        $py = $plotTop;
        $pw = max(0.0, $plotWidth - $labelGutter);
        $ph = max(0.0, $plotHeight - $catGutter);

        $plot = new Plot($px, $py, $pw, $ph, $scale);
        $baselineY = $plot->valueY(max($scale->min, 0.0));

        if ($this->axes) {
            $grid = Paint::stroked(Color::gray(220), 0.4);
            foreach ($scale->ticks() as $tick) {
                $ty = $plot->valueY($tick);
                $this->drawPath($canvas, $ox, $oy, [new Point($px, $ty), new Point($px + $pw, $ty)], $grid);
                $this->drawPath(
                    $canvas,
                    $ox,
                    $oy,
                    [new Point($px - self::TICK_LENGTH_PT, $ty), new Point($px, $ty)],
                    Paint::stroked(Color::gray(120), 0.6),
                );
                $label = $this->encode($this->formatValue($tick, $scale->step));
                $tw = $this->font->metrics->stringWidth($label, $this->labelSizePt);
                $canvas->text(
                    $label,
                    $ox + $px - self::TICK_LENGTH_PT - self::AXIS_GAP_PT - $tw,
                    $oy + $ty + $this->labelSizePt * 0.32,
                    $this->font->index,
                    $this->labelSizePt,
                    $this->textColor,
                );
            }

            $axisPaint = Paint::stroked(Color::gray(90), 0.8);
            $this->drawPath($canvas, $ox, $oy, [new Point($px, $py), new Point($px, $py + $ph)], $axisPaint);
            $this->drawPath(
                $canvas,
                $ox,
                $oy,
                [new Point($px, $baselineY), new Point($px + $pw, $baselineY)],
                $axisPaint,
            );

            foreach ($this->categories as $index => $category) {
                if ($category === '') {
                    continue;
                }
                $encoded = $this->encode($category);
                $tw = $this->font->metrics->stringWidth($encoded, $this->labelSizePt);
                $canvas->text(
                    $encoded,
                    $ox + $plot->slotCentre($index, $slots) - $tw / 2,
                    $oy + $py + $ph + self::AXIS_GAP_PT + $this->labelSizePt * 0.8,
                    $this->font->index,
                    $this->labelSizePt,
                    $this->textColor,
                );
            }
        }

        if ($this->kind === ChartKind::Bar) {
            $this->renderBars($canvas, $ox, $oy, $plot, $slots, $baselineY);

            return;
        }

        foreach ($this->series as $s => $series) {
            $this->drawPath(
                $canvas,
                $ox,
                $oy,
                $plot->line($series->values),
                Paint::stroked($this->seriesColour($s, $series), 1.2),
                closed: false,
            );
        }
    }

    private function renderBars(Canvas $canvas, float $ox, float $oy, Plot $plot, int $slots, float $baselineY): void
    {
        $seriesCount = count($this->series);
        foreach ($this->series as $s => $series) {
            $colour = $this->seriesColour($s, $series);
            foreach ($series->values as $index => $value) {
                [$bx, $bw] = $plot->bar($index, $slots, $s, $seriesCount);
                $valueY = $plot->valueY($value);
                $top = min($valueY, $baselineY);
                $height = abs($baselineY - $valueY);
                $canvas->fillRect($ox + $bx, $oy + $top, $bw, $height, $colour);
            }
        }
    }

    private function renderPie(
        Canvas $canvas,
        float $ox,
        float $oy,
        float $plotLeft,
        float $plotTop,
        float $plotWidth,
        float $plotHeight,
    ): void {
        $values = $this->series[0]->values;
        $side = min($plotWidth, $plotHeight);
        $radius = $side / 2 * 0.94;
        $cx = $plotLeft + $plotWidth / 2;
        $cy = $plotTop + $plotHeight / 2;

        foreach (Plot::pieAngles($values) as $slice => [$startDeg, $endDeg]) {
            $points = [new Point($cx, $cy)];
            $steps = max(2, (int) ceil(($endDeg - $startDeg) / 6.0));
            for ($i = 0; $i <= $steps; $i++) {
                $rad = deg2rad($startDeg + ($endDeg - $startDeg) * $i / $steps);
                $points[] = new Point($cx + $radius * cos($rad), $cy + $radius * sin($rad));
            }

            // A hairline white edge separates touching slices of similar hue.
            $this->drawPath(
                $canvas,
                $ox,
                $oy,
                $points,
                new Paint(fill: Palette::color($slice), stroke: Color::white(), strokeWidthPt: 0.75),
            );
        }
    }

    private function renderSparkline(Canvas $canvas, float $ox, float $oy): void
    {
        $series = $this->series[0];
        $lo = $series->min();
        $hi = $series->max();
        if ($hi - $lo < 1e-9) {
            $hi = $lo + 1.0;
        }

        $inset = 1.5;
        $count = count($series->values);
        $points = [];
        foreach ($series->values as $index => $value) {
            $x = $count > 1 ? $inset + $index / ($count - 1) * ($this->widthPt - 2 * $inset) : $this->widthPt / 2;
            $y = $inset + (1.0 - ($value - $lo) / ($hi - $lo)) * ($this->heightPt - 2 * $inset);
            $points[] = new Point($x, $y);
        }

        $colour = $series->color ?? $this->textColor;
        $this->drawPath($canvas, $ox, $oy, $points, Paint::stroked($colour, 0.9), closed: false);

        $last = $points[$count - 1];
        $canvas->fillRect($ox + $last->x - 1.1, $oy + $last->y - 1.1, 2.2, 2.2, $colour);
    }

    /**
     * @return array{0: float, 1: float, 2: float, 3: float} top, right, bottom, left insets
     */
    private function legendInsets(): array
    {
        $entries = $this->legendEntries();
        if ($entries === []) {
            return [0.0, 0.0, 0.0, 0.0];
        }

        $rowHeight = $this->labelSizePt * 1.5;

        return match ($this->legend) {
            LegendPosition::Right => [0.0, $this->legendWidth($entries) + self::LEGEND_PAD_PT, 0.0, 0.0],
            LegendPosition::Top => [$rowHeight, 0.0, 0.0, 0.0],
            LegendPosition::Bottom => [0.0, 0.0, $rowHeight, 0.0],
            LegendPosition::None => [0.0, 0.0, 0.0, 0.0],
        };
    }

    private function renderLegend(
        Canvas $canvas,
        float $ox,
        float $oy,
        float $plotLeft,
        float $plotTop,
        float $plotWidth,
        float $plotHeight,
    ): void {
        $entries = $this->legendEntries();
        if ($entries === []) {
            return;
        }

        $swatch = $this->labelSizePt;
        $rowHeight = $this->labelSizePt * 1.5;

        if ($this->legend === LegendPosition::Right) {
            $x = $plotLeft + $plotWidth + self::LEGEND_PAD_PT;
            $blockHeight = $rowHeight * count($entries);
            $y = max(0.0, ($this->heightPt - $blockHeight) / 2);
            foreach ($entries as [$label, $colour]) {
                $canvas->fillRect($ox + $x, $oy + $y + ($rowHeight - $swatch) / 2, $swatch, $swatch, $colour);
                $canvas->text(
                    $label,
                    $ox + $x + $swatch + 4.0,
                    $oy + $y + $rowHeight / 2 + $this->labelSizePt * 0.32,
                    $this->font->index,
                    $this->labelSizePt,
                    $this->textColor,
                );
                $y += $rowHeight;
            }

            return;
        }

        $totalWidth = -self::LEGEND_GAP_PT;
        foreach ($entries as [$label]) {
            $totalWidth += self::LEGEND_GAP_PT + $swatch + 4.0
                + $this->font->metrics->stringWidth($label, $this->labelSizePt);
        }

        $x = max(0.0, ($this->widthPt - $totalWidth) / 2);
        $y = $this->legend === LegendPosition::Top ? 0.0 : $this->heightPt - $rowHeight;
        $baseline = $y + $rowHeight / 2 + $this->labelSizePt * 0.32;
        $swatchTop = $y + ($rowHeight - $swatch) / 2;

        foreach ($entries as [$label, $colour]) {
            $canvas->fillRect($ox + $x, $oy + $swatchTop, $swatch, $swatch, $colour);
            $canvas->text(
                $label,
                $ox + $x + $swatch + 4.0,
                $oy + $baseline,
                $this->font->index,
                $this->labelSizePt,
                $this->textColor,
            );
            $x += $swatch + 4.0 + $this->font->metrics->stringWidth($label, $this->labelSizePt) + self::LEGEND_GAP_PT;
        }
    }

    /**
     * @return list<array{0: string, 1: Color}> encoded label, swatch colour
     */
    private function legendEntries(): array
    {
        if ($this->legend === LegendPosition::None) {
            return [];
        }

        if ($this->kind === ChartKind::Pie) {
            $entries = [];
            foreach ($this->series[0]->values as $index => $_value) {
                $label = $this->categories[$index] ?? (string) ($index + 1);
                $entries[] = [$this->encode($label === '' ? (string) ($index + 1) : $label), Palette::color($index)];
            }

            return $entries;
        }

        $named = false;
        foreach ($this->series as $series) {
            if ($series->label !== '') {
                $named = true;
            }
        }
        if (!$named && count($this->series) < 2) {
            return [];
        }

        $entries = [];
        foreach ($this->series as $s => $series) {
            $label = $series->label !== '' ? $series->label : 'Series ' . ($s + 1);
            $entries[] = [$this->encode($label), $this->seriesColour($s, $series)];
        }

        return $entries;
    }

    /** @param list<array{0: string, 1: Color}> $entries */
    private function legendWidth(array $entries): float
    {
        $width = 0.0;
        foreach ($entries as [$label]) {
            $width = max($width, $this->font->metrics->stringWidth($label, $this->labelSizePt));
        }

        return $width + $this->labelSizePt + 4.0;
    }

    private function valueLabelGutter(Scale $scale): float
    {
        $width = 0.0;
        foreach ($scale->ticks() as $tick) {
            $label = $this->encode($this->formatValue($tick, $scale->step));
            $width = max($width, $this->font->metrics->stringWidth($label, $this->labelSizePt));
        }

        return $width + self::TICK_LENGTH_PT + self::AXIS_GAP_PT;
    }

    private function seriesColour(int $index, Series $series): Color
    {
        return $series->color ?? Palette::color($index);
    }

    private function slotCount(): int
    {
        $count = count($this->categories);
        foreach ($this->series as $series) {
            $count = max($count, count($series->values));
        }

        return max(1, $count);
    }

    /**
     * @return array{0: float, 1: float}
     */
    private function dataRange(): array
    {
        $min = INF;
        $max = -INF;
        foreach ($this->series as $series) {
            $min = min($min, $series->min());
            $max = max($max, $series->max());
        }

        return [is_finite($min) ? $min : 0.0, is_finite($max) ? $max : 1.0];
    }

    private function formatValue(float $value, float $step): string
    {
        $decimals = $step >= 1.0 ? 0 : (int) ceil(-log10($step));
        $formatted = number_format($value, $decimals, '.', '');

        return $formatted === '-0' ? '0' : $formatted;
    }

    private function encode(string $text): string
    {
        return Encoding::forFont($text, $this->font->definition->encoding);
    }

    /**
     * @param list<Point> $points
     */
    private function drawPath(Canvas $canvas, float $ox, float $oy, array $points, Paint $paint, bool $closed = true): void
    {
        if (count($points) < 2) {
            return;
        }

        $commands = [];
        foreach ($points as $index => $point) {
            $commands[] = $index === 0
                ? PathCommand::moveTo($point->x, $point->y)
                : PathCommand::lineTo($point->x, $point->y);
        }
        if ($closed) {
            $commands[] = PathCommand::close();
        }

        $canvas->path($commands, $ox, $oy, $paint);
    }
}
