<?php

declare(strict_types=1);

namespace Pdf\Svg;

use Pdf\Color\Color;
use Pdf\Exception\SvgException;
use Pdf\Geometry\PathCommand;
use Pdf\Geometry\PathOp;
use Pdf\Geometry\Point;
use Pdf\Style\Gradient;
use Pdf\Style\GradientSpread;
use Pdf\Style\GradientStop;
use Pdf\Style\LinearGradient;
use Pdf\Style\Paint;
use Pdf\Style\RadialGradient;

/**
 * Turns SVG markup into the {@see SvgShape}s the renderer can already paint.
 *
 * Every element is reduced to {@see PathCommand}s and every `transform` is
 * baked into absolute coordinates as it is walked, so the result is a flat
 * list of figures in points with no residual coordinate system.
 *
 * ## What is supported
 *
 * `path` `rect` `circle` `ellipse` `line` `polyline` `polygon`, grouped with
 * `g` / `a` / `defs` / `symbol` / `use`; the full `transform` syntax; solid
 * fills and strokes with caps, joins and fill rule; `linearGradient` and
 * `radialGradient`; and opacity in all its spellings.
 *
 * ## What is refused, and why it is not ignored
 *
 * Elements that would put ink on the page but have no equivalent here — `text`,
 * `filter`, `mask`, `clipPath`, `pattern`, `marker`, nested viewports — raise
 * an {@see SvgException} rather than being dropped. Skipping them silently
 * produces a picture that is wrong in a way the caller cannot see. Elements
 * that paint nothing (`title`, `desc`, `metadata`, editor metadata in a
 * foreign namespace) are skipped, because dropping them changes nothing.
 *
 * `<style>` is refused for the same reason: a stylesheet that sets `fill`
 * would silently not apply. The `style="…"` *attribute* is fully supported —
 * it needs no cascade, and every drawing tool emits it.
 */
final class SvgParser
{
    private const SVG_NS = 'http://www.w3.org/2000/svg';
    private const XLINK_NS = 'http://www.w3.org/1999/xlink';

    /** The Bézier control ratio approximating a quarter circle, as {@see \Pdf\Node\Path}. */
    private const KAPPA = 0.5523;

    /** A `use` cycle would otherwise recurse forever. */
    private const MAX_USE_DEPTH = 16;

    /** Paints nothing, so dropping it changes nothing. */
    private const SKIPPED = [
        'title', 'desc', 'metadata', 'script', 'defs', 'symbol',
        'lineargradient', 'radialgradient', 'stop', 'view', 'animate',
        'animatetransform', 'animatemotion', 'set', 'mpath', 'desc',
    ];

    /** Would paint, and we cannot reproduce it — refused rather than dropped. */
    private const REFUSED = [
        'text' => 'text', 'tspan' => 'text', 'textpath' => 'text', 'tref' => 'text',
        'altglyph' => 'text', 'filter' => 'filters', 'mask' => 'masks',
        'clippath' => 'clipping paths', 'pattern' => 'pattern fills',
        'marker' => 'markers', 'image' => 'embedded images', 'style' => 'stylesheets',
        'foreignobject' => 'foreign objects', 'switch' => 'conditional rendering',
        'svg' => 'nested viewports',
    ];

    /** @var array<string, \DOMElement> */
    private array $byId = [];

    /** @var list<SvgShape> */
    private array $shapes = [];

    private float $widthPt = 0.0;

    private float $heightPt = 0.0;

    /** The viewBox extent in user units, for resolving percentages. */
    private float $viewportWidth = 0.0;

    private float $viewportHeight = 0.0;

    public function parse(string $xml): SvgDocument
    {
        $this->byId = [];
        $this->shapes = [];

        $root = $this->rootElement($xml);
        $this->index($root);

        $viewBox = $this->viewBox($root);
        $this->resolveSize($root, $viewBox);

        $transform = $this->viewportMatrix(
            $viewBox,
            $this->widthPt,
            $this->heightPt,
            $root->getAttribute('preserveAspectRatio'),
        );
        if ($root->getAttribute('transform') !== '') {
            $transform = $transform->multiply(Matrix::parse($root->getAttribute('transform')));
        }

        $style = (new SvgStyle())->with($this->properties($root));
        $this->children($root, $style, $transform, 0);

        return new SvgDocument($this->shapes, $this->widthPt, $this->heightPt);
    }

    private function rootElement(string $xml): \DOMElement
    {
        if (trim($xml) === '') {
            throw new SvgException('Empty SVG document.');
        }

        $previous = libxml_use_internal_errors(true);
        libxml_clear_errors();

        $document = new \DOMDocument();
        // LIBXML_NONET blocks network fetches, and entity substitution stays
        // off, so a hostile document cannot read local files or call out.
        $loaded = $document->loadXML($xml, LIBXML_NONET | LIBXML_NOCDATA);
        $errors = libxml_get_errors();
        libxml_clear_errors();
        libxml_use_internal_errors($previous);

        if ($loaded === false) {
            $first = $errors === [] ? 'malformed XML' : trim($errors[0]->message);

            throw new SvgException('Could not parse SVG: ' . $first);
        }

        $root = $document->documentElement;
        if (!$root instanceof \DOMElement || strtolower($root->localName) !== 'svg') {
            throw new SvgException('The document root is not an <svg> element.');
        }

        return $root;
    }

    /** Register every `id` up front so `use` and `url(#…)` can resolve forward references. */
    private function index(\DOMElement $element): void
    {
        foreach ($element->childNodes as $child) {
            if (!$child instanceof \DOMElement) {
                continue;
            }
            $id = $child->getAttribute('id');
            if ($id !== '' && !isset($this->byId[$id])) {
                $this->byId[$id] = $child;
            }
            $this->index($child);
        }
    }

    /** @return array{0: float, 1: float, 2: float, 3: float}|null */
    private function viewBox(\DOMElement $root): ?array
    {
        $value = trim($root->getAttribute('viewBox'));
        if ($value === '') {
            return null;
        }

        $parts = preg_split('/[\s,]+/', $value, -1, PREG_SPLIT_NO_EMPTY);
        if ($parts === false || count($parts) !== 4) {
            throw new SvgException('A viewBox needs four numbers: ' . $value);
        }

        $numbers = [];
        foreach ($parts as $part) {
            if (!is_numeric($part)) {
                throw new SvgException('Non-numeric viewBox value: ' . $value);
            }
            $numbers[] = (float) $part;
        }
        if ($numbers[2] <= 0.0 || $numbers[3] <= 0.0) {
            throw new SvgException('A viewBox needs a positive width and height: ' . $value);
        }

        return [$numbers[0], $numbers[1], $numbers[2], $numbers[3]];
    }

    /** @param array{0: float, 1: float, 2: float, 3: float}|null $viewBox */
    private function resolveSize(\DOMElement $root, ?array $viewBox): void
    {
        $this->viewportWidth = $viewBox !== null ? $viewBox[2] : 0.0;
        $this->viewportHeight = $viewBox !== null ? $viewBox[3] : 0.0;

        // A percentage width sizes against a parent viewport this document does
        // not have, so the viewBox is the only intrinsic size available.
        $width = $root->getAttribute('width');
        $height = $root->getAttribute('height');
        $usableWidth = $width !== '' && !SvgLength::isPercentage($width);
        $usableHeight = $height !== '' && !SvgLength::isPercentage($height);

        if ($usableWidth && $usableHeight) {
            $this->widthPt = SvgLength::points($width);
            $this->heightPt = SvgLength::points($height);
        } elseif ($viewBox !== null) {
            // One stated dimension still fixes the scale; the other follows the
            // viewBox aspect ratio.
            $aspect = $viewBox[2] / $viewBox[3];
            if ($usableWidth) {
                $this->widthPt = SvgLength::points($width);
                $this->heightPt = $this->widthPt / $aspect;
            } elseif ($usableHeight) {
                $this->heightPt = SvgLength::points($height);
                $this->widthPt = $this->heightPt * $aspect;
            } else {
                $this->widthPt = $viewBox[2] * SvgLength::POINTS_PER_USER_UNIT;
                $this->heightPt = $viewBox[3] * SvgLength::POINTS_PER_USER_UNIT;
            }
        } else {
            throw new SvgException('The <svg> element needs a width and height, or a viewBox, to be sized.');
        }

        if ($this->widthPt <= 0.0 || $this->heightPt <= 0.0) {
            throw new SvgException('The <svg> element has a zero or negative size.');
        }

        if ($viewBox === null) {
            $this->viewportWidth = $this->widthPt / SvgLength::POINTS_PER_USER_UNIT;
            $this->viewportHeight = $this->heightPt / SvgLength::POINTS_PER_USER_UNIT;
        }
    }

    /**
     * The user-space-to-points transform, honouring `preserveAspectRatio`.
     *
     * @param array{0: float, 1: float, 2: float, 3: float}|null $viewBox
     */
    private function viewportMatrix(?array $viewBox, float $widthPt, float $heightPt, string $preserve): Matrix
    {
        if ($viewBox === null) {
            return Matrix::scaling(SvgLength::POINTS_PER_USER_UNIT, SvgLength::POINTS_PER_USER_UNIT);
        }

        [$minX, $minY, $boxWidth, $boxHeight] = $viewBox;
        $scaleX = $widthPt / $boxWidth;
        $scaleY = $heightPt / $boxHeight;

        $tokens = preg_split('/\s+/', strtolower(trim($preserve)), -1, PREG_SPLIT_NO_EMPTY);
        $tokens = $tokens === false ? [] : $tokens;
        // The optional leading `defer` keyword shifts everything after it.
        if (isset($tokens[0]) && $tokens[0] === 'defer') {
            array_shift($tokens);
        }
        $align = $tokens === [] ? 'xmidymid' : $tokens[0];
        $slice = isset($tokens[1]) && $tokens[1] === 'slice';

        if ($align === 'none') {
            return Matrix::scaling($scaleX, $scaleY)->multiply(Matrix::translate(-$minX, -$minY));
        }

        $scale = $slice ? max($scaleX, $scaleY) : min($scaleX, $scaleY);
        $offsetX = self::alignFactor($align, 'x') * ($widthPt - $boxWidth * $scale);
        $offsetY = self::alignFactor($align, 'y') * ($heightPt - $boxHeight * $scale);

        return Matrix::translate($offsetX, $offsetY)
            ->multiply(Matrix::scaling($scale, $scale))
            ->multiply(Matrix::translate(-$minX, -$minY));
    }

    /** 0, 0.5 or 1 for the Min / Mid / Max part of a preserveAspectRatio alignment. */
    private static function alignFactor(string $align, string $axis): float
    {
        $marker = $axis === 'x' ? 'x' : 'y';
        $position = strpos($align, $marker, $axis === 'x' ? 0 : 1);
        if ($position === false) {
            return 0.5;
        }

        $keyword = substr($align, $position + 1, 3);

        return match ($keyword) {
            'min' => 0.0,
            'max' => 1.0,
            default => 0.5,
        };
    }

    private function children(\DOMElement $element, SvgStyle $style, Matrix $transform, int $depth): void
    {
        foreach ($element->childNodes as $child) {
            if ($child instanceof \DOMElement) {
                $this->walk($child, $style, $transform, $depth);
            }
        }
    }

    private function walk(\DOMElement $element, SvgStyle $parentStyle, Matrix $parentTransform, int $depth): void
    {
        // Editor metadata (inkscape:, sodipodi:, rdf:) paints nothing.
        if ($element->namespaceURI !== null && $element->namespaceURI !== self::SVG_NS) {
            return;
        }

        $name = strtolower($element->localName);
        if (in_array($name, self::SKIPPED, true)) {
            return;
        }
        if (isset(self::REFUSED[$name])) {
            throw new SvgException(sprintf('Unsupported SVG feature: %s (<%s>).', self::REFUSED[$name], $name));
        }

        $style = $parentStyle->with($this->properties($element));
        if ($style->hidden) {
            return;
        }

        $transform = $parentTransform;
        $own = $element->getAttribute('transform');
        if ($own !== '') {
            $transform = $transform->multiply(Matrix::parse($own));
        }

        switch ($name) {
            case 'g':
            case 'a':
                $this->children($element, $style, $transform, $depth);

                return;

            case 'use':
                $this->useReference($element, $style, $transform, $depth);

                return;

            case 'path':
                $this->emit(PathData::parse($element->getAttribute('d')), $style, $transform);

                return;

            case 'rect':
                $this->emit($this->rectangle($element), $style, $transform);

                return;

            case 'circle':
            case 'ellipse':
                $this->emit($this->ellipse($element, $name === 'circle'), $style, $transform);

                return;

            case 'line':
                $this->emit([
                    PathCommand::moveTo($this->number($element, 'x1'), $this->number($element, 'y1')),
                    PathCommand::lineTo($this->number($element, 'x2'), $this->number($element, 'y2')),
                ], $style, $transform);

                return;

            case 'polyline':
            case 'polygon':
                $this->emit($this->polygon($element, $name === 'polygon'), $style, $transform);

                return;

            default:
                throw new SvgException(sprintf('Unsupported SVG element: <%s>.', $element->localName));
        }
    }

    private function useReference(\DOMElement $element, SvgStyle $style, Matrix $transform, int $depth): void
    {
        if ($depth >= self::MAX_USE_DEPTH) {
            throw new SvgException('SVG <use> nested too deeply — the document probably references itself.');
        }

        $href = $element->getAttribute('href');
        if ($href === '') {
            $href = $element->getAttributeNS(self::XLINK_NS, 'href');
        }
        if (!str_starts_with($href, '#')) {
            throw new SvgException('A <use> element must reference a local id, got: ' . $href);
        }

        $target = $this->byId[substr($href, 1)] ?? throw new SvgException('Unknown <use> reference: ' . $href);

        $transform = $transform->multiply(
            Matrix::translate($this->number($element, 'x'), $this->number($element, 'y')),
        );

        if (strtolower($target->localName) === 'symbol') {
            // A symbol is a viewport of its own: its viewBox maps onto the size
            // the `use` asks for, which is how an icon sprite is drawn.
            $viewBox = $this->viewBox($target);
            $width = $element->getAttribute('width');
            $height = $element->getAttribute('height');
            if ($viewBox !== null && $width !== '' && $height !== '') {
                $transform = $transform->multiply($this->viewportMatrix(
                    $viewBox,
                    SvgLength::userUnits($width),
                    SvgLength::userUnits($height),
                    $target->getAttribute('preserveAspectRatio'),
                ));
            }
            $this->children($target, $style->with($this->properties($target)), $transform, $depth + 1);

            return;
        }

        $this->walk($target, $style, $transform, $depth + 1);
    }

    /** @return list<PathCommand> */
    private function rectangle(\DOMElement $element): array
    {
        $x = $this->number($element, 'x');
        $y = $this->number($element, 'y');
        $width = $this->number($element, 'width');
        $height = $this->number($element, 'height');
        if ($width <= 0.0 || $height <= 0.0) {
            return [];
        }

        // An omitted rx mirrors ry, and vice versa; both clamp to half the side.
        $hasRx = $element->getAttribute('rx') !== '' && strtolower($element->getAttribute('rx')) !== 'auto';
        $hasRy = $element->getAttribute('ry') !== '' && strtolower($element->getAttribute('ry')) !== 'auto';
        $rx = $hasRx ? $this->number($element, 'rx') : ($hasRy ? $this->number($element, 'ry') : 0.0);
        $ry = $hasRy ? $this->number($element, 'ry') : $rx;
        $rx = min(max($rx, 0.0), $width / 2);
        $ry = min(max($ry, 0.0), $height / 2);

        if ($rx <= 0.0 || $ry <= 0.0) {
            return [
                PathCommand::moveTo($x, $y),
                PathCommand::lineTo($x + $width, $y),
                PathCommand::lineTo($x + $width, $y + $height),
                PathCommand::lineTo($x, $y + $height),
                PathCommand::close(),
            ];
        }

        $cx = $rx * self::KAPPA;
        $cy = $ry * self::KAPPA;
        $right = $x + $width;
        $bottom = $y + $height;

        return [
            PathCommand::moveTo($x + $rx, $y),
            PathCommand::lineTo($right - $rx, $y),
            PathCommand::curveTo($right - $rx + $cx, $y, $right, $y + $ry - $cy, $right, $y + $ry),
            PathCommand::lineTo($right, $bottom - $ry),
            PathCommand::curveTo($right, $bottom - $ry + $cy, $right - $rx + $cx, $bottom, $right - $rx, $bottom),
            PathCommand::lineTo($x + $rx, $bottom),
            PathCommand::curveTo($x + $rx - $cx, $bottom, $x, $bottom - $ry + $cy, $x, $bottom - $ry),
            PathCommand::lineTo($x, $y + $ry),
            PathCommand::curveTo($x, $y + $ry - $cy, $x + $rx - $cx, $y, $x + $rx, $y),
            PathCommand::close(),
        ];
    }

    /** @return list<PathCommand> */
    private function ellipse(\DOMElement $element, bool $circular): array
    {
        $cx = $this->number($element, 'cx');
        $cy = $this->number($element, 'cy');
        if ($circular) {
            $rx = $ry = $this->number($element, 'r');
        } else {
            $rx = $this->number($element, 'rx');
            $ry = $this->number($element, 'ry');
        }
        if ($rx <= 0.0 || $ry <= 0.0) {
            return [];
        }

        $ox = $rx * self::KAPPA;
        $oy = $ry * self::KAPPA;

        return [
            PathCommand::moveTo($cx + $rx, $cy),
            PathCommand::curveTo($cx + $rx, $cy + $oy, $cx + $ox, $cy + $ry, $cx, $cy + $ry),
            PathCommand::curveTo($cx - $ox, $cy + $ry, $cx - $rx, $cy + $oy, $cx - $rx, $cy),
            PathCommand::curveTo($cx - $rx, $cy - $oy, $cx - $ox, $cy - $ry, $cx, $cy - $ry),
            PathCommand::curveTo($cx + $ox, $cy - $ry, $cx + $rx, $cy - $oy, $cx + $rx, $cy),
            PathCommand::close(),
        ];
    }

    /** @return list<PathCommand> */
    private function polygon(\DOMElement $element, bool $closed): array
    {
        $parts = preg_split('/[\s,]+/', trim($element->getAttribute('points')), -1, PREG_SPLIT_NO_EMPTY);
        if ($parts === false || count($parts) < 4) {
            return [];
        }

        $commands = [];
        // An odd trailing coordinate is dropped, as the SVG error rules require.
        $pairs = intdiv(count($parts), 2);
        for ($index = 0; $index < $pairs; $index++) {
            $x = $parts[$index * 2];
            $y = $parts[$index * 2 + 1];
            if (!is_numeric($x) || !is_numeric($y)) {
                throw new SvgException('Non-numeric point in: ' . $element->getAttribute('points'));
            }
            $commands[] = $index === 0
                ? PathCommand::moveTo((float) $x, (float) $y)
                : PathCommand::lineTo((float) $x, (float) $y);
        }
        if ($closed) {
            $commands[] = PathCommand::close();
        }

        return $commands;
    }

    /**
     * Bake the transform into the geometry and record the finished shape.
     *
     * @param list<PathCommand> $commands in user space
     */
    private function emit(array $commands, SvgStyle $style, Matrix $transform): void
    {
        if ($commands === [] || $style->paintsNothing()) {
            return;
        }

        $paint = $this->paint($style, $commands, $transform);
        if ($paint === null || $paint->operator() === null) {
            return;
        }

        $placed = [];
        foreach ($commands as $command) {
            $placed[] = self::transformCommand($command, $transform);
        }

        $this->shapes[] = new SvgShape($placed, $paint);
    }

    private static function transformCommand(PathCommand $command, Matrix $matrix): PathCommand
    {
        $points = [];
        foreach ($command->points as $point) {
            $points[] = $matrix->apply($point);
        }

        return match ($command->op) {
            PathOp::MoveTo => PathCommand::moveTo($points[0]->x, $points[0]->y),
            PathOp::LineTo => PathCommand::lineTo($points[0]->x, $points[0]->y),
            PathOp::CurveTo => PathCommand::curveTo(
                $points[0]->x,
                $points[0]->y,
                $points[1]->x,
                $points[1]->y,
                $points[2]->x,
                $points[2]->y,
            ),
            PathOp::Close => $command,
        };
    }

    /**
     * The paint for a shape, or null when it resolves to no paint at all —
     * a gradient reference with no stops, say. {@see Paint} falls back to a
     * hairline black outline when handed neither half, which would put ink on
     * the page that the SVG never asked for.
     *
     * @param list<PathCommand> $commands in user space
     */
    private function paint(SvgStyle $style, array $commands, Matrix $transform): ?Paint
    {
        $fill = $style->fill;
        if ($style->fillGradient !== null) {
            $fill = $this->gradient($style->fillGradient, $commands, $transform);
        }

        if ($fill === null && $style->stroke === null) {
            return null;
        }

        return new Paint(
            $fill,
            $style->stroke,
            $style->strokeWidth * $transform->meanScale(),
            $style->fillRule,
            $style->lineCap,
            $style->lineJoin,
            $style->effectiveFillAlpha(),
            $style->effectiveStrokeAlpha(),
        );
    }

    /**
     * Resolve `url(#id)` to a {@see Gradient} expressed as fractions of the
     * whole SVG box, which is the box {@see \Pdf\Layout\Box\SvgBox} hands the
     * renderer for every shape.
     *
     * @param list<PathCommand> $commands in user space
     */
    private function gradient(string $id, array $commands, Matrix $transform): Color|Gradient|null
    {
        $element = $this->byId[$id] ?? throw new SvgException('Unknown gradient reference: #' . $id);
        $name = strtolower($element->localName);
        if ($name !== 'lineargradient' && $name !== 'radialgradient') {
            throw new SvgException('A paint reference must point at a gradient, #' . $id . ' is a <' . $element->localName . '>.');
        }

        $stops = $this->stops($element, 0);
        if ($stops === []) {
            return null;
        }
        if (count($stops) === 1) {
            // A one-stop gradient is a solid colour; Gradient requires two.
            return $stops[0]->color;
        }

        $spread = strtolower($this->inherited($element, 'spreadMethod', 0) ?? 'pad');
        if ($spread !== 'pad' && $spread !== '') {
            throw new SvgException('Unsupported SVG gradient spreadMethod: ' . $spread);
        }

        $units = strtolower($this->inherited($element, 'gradientUnits', 0) ?? 'objectboundingbox');
        $bounds = $units === 'userspaceonuse' ? null : self::bounds($commands);

        // Gradient coordinates live either in the unit square of the shape's
        // own bounding box (the default) or in user space. `gradientTransform`
        // acts inside that space, so it composes innermost — before the box
        // mapping, and before the element transform that carries both to the
        // page. Getting this order wrong misplaces every transformed gradient.
        $toPage = $transform;
        if ($bounds !== null) {
            $toPage = $toPage
                ->multiply(Matrix::translate($bounds[0], $bounds[1]))
                ->multiply(Matrix::scaling(
                    max($bounds[2] - $bounds[0], 1e-9),
                    max($bounds[3] - $bounds[1], 1e-9),
                ));
        }

        $gradientTransform = $this->inherited($element, 'gradientTransform', 0);
        if ($gradientTransform !== null && trim($gradientTransform) !== '') {
            $toPage = $toPage->multiply(Matrix::parse($gradientTransform));
        }

        if ($name === 'lineargradient') {
            [$x0, $y0] = $this->gradientPoint($element, 'x1', 'y1', 0.0, 0.0, $bounds, $toPage);
            [$x1, $y1] = $this->gradientPoint($element, 'x2', 'y2', 1.0, 0.0, $bounds, $toPage);

            return LinearGradient::between($stops, $x0, $y0, $x1, $y1, GradientSpread::Pad);
        }

        [$cx, $cy] = $this->gradientPoint($element, 'cx', 'cy', 0.5, 0.5, $bounds, $toPage);
        [$fx, $fy] = $this->gradientPoint(
            $element,
            'fx',
            'fy',
            $this->gradientFraction($element, 'cx', 0.5, $bounds, true),
            $this->gradientFraction($element, 'cy', 0.5, $bounds, false),
            $bounds,
            $toPage,
        );

        // A PDF radial shading is circular; an SVG one stretches with a
        // non-square bounding box. The larger box dimension is the reference,
        // matching how RadialGradient resolves its own radius.
        $radius = $this->gradientFraction($element, 'r', 0.5, $bounds, true)
            * $toPage->meanScale()
            / max($this->widthPt, $this->heightPt);

        return RadialGradient::focused($stops, $fx, $fy, $cx, $cy, $radius, 0.0, GradientSpread::Pad);
    }

    /**
     * A gradient coordinate pair, mapped from its own units all the way to a
     * fraction of the SVG box.
     *
     * @param array{0: float, 1: float, 2: float, 3: float}|null $bounds
     * @return array{0: float, 1: float}
     */
    private function gradientPoint(
        \DOMElement $element,
        string $xName,
        string $yName,
        float $defaultX,
        float $defaultY,
        ?array $bounds,
        Matrix $toPage,
    ): array {
        $x = $this->gradientFraction($element, $xName, $defaultX, $bounds, true);
        $y = $this->gradientFraction($element, $yName, $defaultY, $bounds, false);
        $point = $toPage->apply(new Point($x, $y));

        return [
            $this->widthPt > 0.0 ? $point->x / $this->widthPt : 0.0,
            $this->heightPt > 0.0 ? $point->y / $this->heightPt : 0.0,
        ];
    }

    /** @param array{0: float, 1: float, 2: float, 3: float}|null $bounds */
    private function gradientFraction(
        \DOMElement $element,
        string $name,
        float $default,
        ?array $bounds,
        bool $horizontal,
    ): float {
        $value = $this->inherited($element, $name, 0);
        if ($value === null || trim($value) === '') {
            return $default;
        }

        $value = trim($value);
        if (SvgLength::isPercentage($value)) {
            $ratio = SvgColor::opacity($value);

            return $bounds === null
                ? $ratio * ($horizontal ? $this->viewportWidth : $this->viewportHeight)
                : $ratio;
        }

        return SvgLength::userUnits($value);
    }

    /**
     * An attribute from this gradient or, failing that, from the one it
     * inherits through `href` — how tools emit a shared gradient definition.
     */
    private function inherited(\DOMElement $element, string $name, int $depth): ?string
    {
        if ($element->hasAttribute($name)) {
            return $element->getAttribute($name);
        }

        $parent = $this->hrefTarget($element, $depth);

        return $parent === null ? null : $this->inherited($parent, $name, $depth + 1);
    }

    /** @return list<GradientStop> */
    private function stops(\DOMElement $element, int $depth): array
    {
        $stops = [];
        $previous = 0.0;
        foreach ($element->childNodes as $child) {
            if (!$child instanceof \DOMElement || strtolower($child->localName) !== 'stop') {
                continue;
            }

            $properties = $this->properties($child);
            $opacity = isset($properties['stop-opacity'])
                ? SvgColor::opacity($properties['stop-opacity'])
                : 1.0;
            if ($opacity < 1.0) {
                // A PDF shading interpolates colour only; there is nowhere to
                // put a per-stop alpha.
                throw new SvgException('Unsupported SVG feature: per-stop gradient opacity (stop-opacity).');
            }

            $offsetValue = $properties['offset'] ?? '0';
            $offset = SvgLength::isPercentage($offsetValue)
                ? SvgColor::opacity($offsetValue)
                : max(0.0, min(1.0, SvgLength::userUnits($offsetValue)));
            // SVG clamps a stop that would run backwards up to its predecessor.
            $offset = max($offset, $previous);
            $previous = $offset;

            $color = SvgColor::parse($properties['stop-color'] ?? '#000000') ?? Color::black();
            $stops[] = new GradientStop($offset, $color);
        }

        if ($stops === []) {
            $parent = $this->hrefTarget($element, $depth);
            if ($parent !== null) {
                return $this->stops($parent, $depth + 1);
            }
        }

        return $stops;
    }

    private function hrefTarget(\DOMElement $element, int $depth): ?\DOMElement
    {
        if ($depth >= self::MAX_USE_DEPTH) {
            throw new SvgException('Gradient href chain nested too deeply.');
        }

        $href = $element->getAttribute('href');
        if ($href === '') {
            $href = $element->getAttributeNS(self::XLINK_NS, 'href');
        }
        if (!str_starts_with($href, '#')) {
            return null;
        }

        return $this->byId[substr($href, 1)] ?? null;
    }

    /**
     * The bounding box of user-space geometry.
     *
     * Bézier control points are included, so a curved shape's box can be a
     * little larger than the tight box SVG specifies. It only shifts where a
     * gradient's ends land, never whether it renders.
     *
     * @param list<PathCommand> $commands
     * @return array{0: float, 1: float, 2: float, 3: float}
     */
    private static function bounds(array $commands): array
    {
        $minX = $minY = INF;
        $maxX = $maxY = -INF;
        foreach ($commands as $command) {
            foreach ($command->points as $point) {
                $minX = min($minX, $point->x);
                $minY = min($minY, $point->y);
                $maxX = max($maxX, $point->x);
                $maxY = max($maxY, $point->y);
            }
        }

        if ($minX > $maxX) {
            return [0.0, 0.0, 0.0, 0.0];
        }

        return [$minX, $minY, $maxX, $maxY];
    }

    /**
     * An element's presentation properties: its attributes, with the inline
     * `style` attribute layered on top as CSS specificity requires.
     *
     * @return array<string, string>
     */
    private function properties(\DOMElement $element): array
    {
        $properties = [];
        foreach ($element->attributes as $attribute) {
            // Editor attributes (inkscape:label &c.) are not presentation.
            if ($attribute->namespaceURI !== null && $attribute->namespaceURI !== self::SVG_NS) {
                continue;
            }
            $properties[strtolower($attribute->localName)] = $attribute->value;
        }

        $inline = $element->getAttribute('style');
        if (trim($inline) !== '') {
            foreach (explode(';', $inline) as $declaration) {
                $colon = strpos($declaration, ':');
                if ($colon === false) {
                    continue;
                }
                $name = strtolower(trim(substr($declaration, 0, $colon)));
                if ($name !== '') {
                    $properties[$name] = trim(substr($declaration, $colon + 1));
                }
            }
        }

        return $properties;
    }

    private function number(\DOMElement $element, string $name, float $default = 0.0): float
    {
        $value = trim($element->getAttribute($name));
        if ($value === '') {
            return $default;
        }

        if (SvgLength::isPercentage($value)) {
            $ratio = SvgColor::opacity($value);
            $basis = match ($name) {
                'x', 'x1', 'x2', 'cx', 'width', 'rx' => $this->viewportWidth,
                'y', 'y1', 'y2', 'cy', 'height', 'ry' => $this->viewportHeight,
                // A lone radius resolves against the viewport diagonal.
                default => sqrt(($this->viewportWidth ** 2 + $this->viewportHeight ** 2) / 2),
            };

            return $ratio * $basis;
        }

        return SvgLength::userUnits($value);
    }
}
