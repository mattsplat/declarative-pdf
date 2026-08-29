<?php

declare(strict_types=1);

namespace Pdf\Render;

use Pdf\Color\Color;
use Pdf\Geometry\Edges;
use Pdf\Geometry\PageGeometry;
use Pdf\Geometry\PathCommand;
use Pdf\Layout\AnchorMark;
use Pdf\Layout\Canvas;
use Pdf\Layout\LinkRect;
use Pdf\Style\FillRule;
use Pdf\Style\Gradient;
use Pdf\Style\Paint;

/**
 * Accumulates the content-stream operators for a single page and satisfies the
 * {@see Canvas} contract the layout boxes draw through.
 *
 * Replaces the scattered `_out(sprintf(...))` calls in `Cell()` (fpdf.php:637),
 * `Rect()` (fpdf.php:431) and `SetFont()` (fpdf.php:522). The Y axis is flipped
 * here and nowhere else, via {@see PageGeometry::flipY()}.
 */
final class ContentStream implements Canvas
{
    private string $buffer = '';

    /** @var list<LinkRect> */
    private array $links = [];

    /** @var list<AnchorMark> */
    private array $anchors = [];

    /** @var list<ShadingResource> */
    private array $shadings = [];

    private readonly ResourceNamer $namer;

    public function __construct(
        private readonly PageGeometry $geometry,
        bool $emitPreamble = true,
        ?ResourceNamer $namer = null,
    ) {
        $this->namer = $namer ?? new ResourceNamer();

        if ($emitPreamble) {
            // Square line caps, matching AddPage() (fpdf.php:312).
            $this->raw('2 J');
        }
    }

    public function raw(string $operators): void
    {
        $this->buffer .= $operators . "\n";
    }

    public function text(
        string $text,
        float $xPt,
        float $baselineYFromTopPt,
        int $fontIndex,
        float $sizePt,
        Color $color,
        ?float $wordSpacingPt = null,
    ): void {
        if ($text === '') {
            return;
        }

        $y = $this->geometry->flipY($baselineYFromTopPt);
        $wrapColour = !$color->equals(Color::black());
        $hasWordSpacing = $wordSpacingPt !== null && $wordSpacingPt > 0.0;

        $s = '';
        if ($wrapColour) {
            $s .= 'q ' . $color->textOp() . ' ';
        }
        $s .= sprintf('BT /F%d %.2F Tf ', $fontIndex, $sizePt);
        if ($hasWordSpacing) {
            $s .= sprintf('%.3F Tw ', $wordSpacingPt);
        }
        $s .= sprintf('%.2F %.2F Td (%s) Tj ET', $xPt, $y, PdfString::escape($text));
        if ($hasWordSpacing) {
            $s .= ' BT 0 Tw ET';
        }
        if ($wrapColour) {
            $s .= ' Q';
        }
        $this->raw($s);
    }

    public function fillRect(float $xPt, float $yTopPt, float $widthPt, float $heightPt, Color $color): void
    {
        if ($widthPt <= 0.0 || $heightPt <= 0.0) {
            return;
        }

        $yBottom = $this->geometry->flipY($yTopPt + $heightPt);
        $this->raw(sprintf(
            'q %s %.2F %.2F %.2F %.2F re f Q',
            $color->fillOp(),
            $xPt,
            $yBottom,
            $widthPt,
            $heightPt,
        ));
    }

    public function strokeEdges(
        float $xPt,
        float $yTopPt,
        float $widthPt,
        float $heightPt,
        Edges $edgeWidthsPt,
        Color $color,
    ): void {
        if ($edgeWidthsPt->top > 0.0) {
            $this->fillRect($xPt, $yTopPt, $widthPt, $edgeWidthsPt->top, $color);
        }
        if ($edgeWidthsPt->bottom > 0.0) {
            $this->fillRect($xPt, $yTopPt + $heightPt - $edgeWidthsPt->bottom, $widthPt, $edgeWidthsPt->bottom, $color);
        }
        if ($edgeWidthsPt->left > 0.0) {
            $this->fillRect($xPt, $yTopPt, $edgeWidthsPt->left, $heightPt, $color);
        }
        if ($edgeWidthsPt->right > 0.0) {
            $this->fillRect($xPt + $widthPt - $edgeWidthsPt->right, $yTopPt, $edgeWidthsPt->right, $heightPt, $color);
        }
    }

    public function horizontalLine(float $x1Pt, float $x2Pt, float $yPt, float $lineWidthPt, Color $color): void
    {
        $left = min($x1Pt, $x2Pt);
        $this->fillRect($left, $yPt - $lineWidthPt / 2, abs($x2Pt - $x1Pt), $lineWidthPt, $color);
    }

    /**
     * Emit a painted path. Each operand is translated by ($xPt, $yTopPt) and
     * then flipped, so the caller only ever thinks top-down. A gradient fill is
     * painted as a shading clipped to the path; `$boxWidthPt` / `$boxHeightPt`
     * are the path's own box and resolve the gradient's fractional geometry.
     *
     * @param list<PathCommand> $commands
     */
    public function path(
        array $commands,
        float $xPt,
        float $yTopPt,
        Paint $paint,
        float $boxWidthPt = 0.0,
        float $boxHeightPt = 0.0,
    ): void {
        if ($commands === []) {
            return;
        }

        $commandLines = $this->pathCommandLines($commands, $xPt, $yTopPt);
        $fill = $paint->fill;

        if ($fill instanceof Gradient) {
            $this->fillWithGradient($fill, $commandLines, $paint->fillRule, $xPt, $yTopPt, $boxWidthPt, $boxHeightPt);
            if ($paint->strokes()) {
                $this->strokeOnly($paint, $commandLines);
            }

            return;
        }

        $painter = $paint->operator();
        if ($painter === null) {
            return;
        }

        $stroke = $paint->strokes() ? $paint->stroke : null;

        $state = '';
        if ($fill instanceof Color) {
            $state .= $fill->fillOp() . ' ';
        }
        if ($stroke !== null) {
            // The page preamble leaves `2 J` in effect, so the cap and join are
            // always stated rather than inherited.
            $state .= sprintf(
                '%s %.2F w %d J %d j ',
                $stroke->strokeOp(),
                $paint->strokeWidthPt,
                $paint->lineCap->value,
                $paint->lineJoin->value,
            );
        }

        $lines = ['q ' . rtrim($state), ...$commandLines, $painter, 'Q'];
        $this->raw(implode("\n", $lines));
    }

    /**
     * The path-construction operators for $commands, each operand translated by
     * ($xPt, $yTopPt) and then flipped.
     *
     * @param list<PathCommand> $commands
     * @return list<string>
     */
    private function pathCommandLines(array $commands, float $xPt, float $yTopPt): array
    {
        $lines = [];
        foreach ($commands as $command) {
            $operands = '';
            foreach ($command->points as $point) {
                $operands .= sprintf(
                    '%.2F %.2F ',
                    $xPt + $point->x,
                    $this->geometry->flipY($yTopPt + $point->y),
                );
            }
            $lines[] = $operands . $command->op->operator();
        }

        return $lines;
    }

    /**
     * Register a shading for $gradient and emit `q … W n /Sh… sh Q`: the path
     * becomes the clip and `sh` paints the gradient across it. `sh` honours the
     * CTM, so a placed path's enclosing `cm` transforms the gradient with it.
     *
     * @param list<string> $commandLines
     */
    private function fillWithGradient(
        Gradient $gradient,
        array $commandLines,
        FillRule $fillRule,
        float $xPt,
        float $yTopPt,
        float $boxWidthPt,
        float $boxHeightPt,
    ): void {
        $name = $this->namer->next('Sh');
        $place = fn (float $x, float $y): array => [$xPt + $x, $this->geometry->flipY($yTopPt + $y)];
        $coords = $gradient->coords($place, $boxWidthPt, $boxHeightPt);

        $this->shadings[] = new ShadingResource($name, sprintf(
            '<</ShadingType %d /ColorSpace /DeviceRGB /Coords [%s] /Function %s /Extend [%s]>>',
            $gradient->shadingType(),
            implode(' ', array_map(static fn (float $n): string => sprintf('%.2F', $n), $coords)),
            $gradient->functionDictionary(),
            $gradient->spread->extendArray(),
        ));

        $lines = ['q', ...$commandLines, 'W' . $fillRule->operatorSuffix() . ' n', '/' . $name . ' sh', 'Q'];
        $this->raw(implode("\n", $lines));
    }

    /**
     * Stroke $commandLines on their own — the second pass for a gradient-filled
     * path that also has a stroke.
     *
     * @param list<string> $commandLines
     */
    private function strokeOnly(Paint $paint, array $commandLines): void
    {
        $stroke = $paint->stroke;
        if ($stroke === null) {
            return;
        }

        $state = sprintf(
            '%s %.2F w %d J %d j',
            $stroke->strokeOp(),
            $paint->strokeWidthPt,
            $paint->lineCap->value,
            $paint->lineJoin->value,
        );
        $this->raw(implode("\n", ['q ' . $state, ...$commandLines, 'S', 'Q']));
    }

    /**
     * Run $draw with a rectangular clip active (used for `Fit::Cover` /
     * `Fit::ActualSize` placements).
     */
    public function withClip(float $xPt, float $yTopPt, float $widthPt, float $heightPt, \Closure $draw): void
    {
        $yBottom = $this->geometry->flipY($yTopPt + $heightPt);
        $this->raw(sprintf('q %.2F %.2F %.2F %.2F re W n', $xPt, $yBottom, $widthPt, $heightPt));
        $draw();
        $this->raw('Q');
    }

    /**
     * Run $draw with an arbitrary command list as the clip region. Coordinates
     * are relative to ($xPt, $yTopPt), the same convention as {@see path()}.
     *
     * @param list<PathCommand> $commands
     */
    public function withPathClip(
        array $commands,
        float $xPt,
        float $yTopPt,
        FillRule $rule,
        \Closure $draw,
    ): void {
        if ($commands === []) {
            $draw();

            return;
        }

        $lines = ['q', ...$this->pathCommandLines($commands, $xPt, $yTopPt), 'W' . $rule->operatorSuffix() . ' n'];
        $this->raw(implode("\n", $lines));
        $draw();
        $this->raw('Q');
    }

    public function image(int $imageIndex, float $xPt, float $yTopPt, float $widthPt, float $heightPt): void
    {
        if ($widthPt <= 0.0 || $heightPt <= 0.0) {
            return;
        }

        // Ports Image() (fpdf.php:928): q w 0 0 h x y cm /I{i} Do Q
        $this->raw(sprintf(
            'q %.2F 0 0 %.2F %.2F %.2F cm /I%d Do Q',
            $widthPt,
            $heightPt,
            $xPt,
            $this->geometry->flipY($yTopPt + $heightPt),
            $imageIndex,
        ));
    }

    /** Invoke an imported-page Form XObject with an explicit transform matrix. */
    public function formXObject(int $index, float $a, float $b, float $c, float $d, float $e, float $f): void
    {
        $this->raw(sprintf('q %.5F %.5F %.5F %.5F %.4F %.4F cm /Import%d Do Q', $a, $b, $c, $d, $e, $f, $index));
    }

    public function link(float $xPt, float $yTopPt, float $widthPt, float $heightPt, string $target): void
    {
        $this->links[] = new LinkRect($xPt, $yTopPt, $widthPt, $heightPt, $target);
    }

    public function anchor(string $name, float $yTopPt): void
    {
        $this->anchors[] = new AnchorMark($name, $yTopPt);
    }

    /** Adopt a shading collected from a nested stream (e.g. a placed area). */
    public function recordShading(ShadingResource $shading): void
    {
        $this->shadings[] = $shading;
    }

    /** @return list<ShadingResource> */
    public function collectedShadings(): array
    {
        return $this->shadings;
    }

    /** @return list<LinkRect> */
    public function collectedLinks(): array
    {
        return $this->links;
    }

    /** @return list<AnchorMark> */
    public function collectedAnchors(): array
    {
        return $this->anchors;
    }

    public function toString(): string
    {
        return rtrim($this->buffer, "\n");
    }
}
