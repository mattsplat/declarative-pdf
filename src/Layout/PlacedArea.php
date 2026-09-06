<?php

declare(strict_types=1);

namespace Pdf\Layout;

use Pdf\Color\Color;
use Pdf\Geometry\BoxAlign;
use Pdf\Geometry\Fit;
use Pdf\Geometry\Rect;
use Pdf\Import\ImportedPage;
use Pdf\Layout\Box\StackBox;
use Pdf\Style\Border;
use Pdf\Svg\SvgShape;

/**
 * An absolutely-positioned area, resolved and ready to render: its rectangle
 * plus exactly one kind of content.
 */
final readonly class PlacedArea
{
    private function __construct(
        public Rect $rectPt,
        public Fit $fit,
        public BoxAlign $align,
        public ?StackBox $blocks = null,
        public float $blocksNaturalHeightPt = 0.0,
        /** False disables the renderer's geometric shrink (ShrinkMode::None). */
        public bool $blocksGeometricShrink = true,
        public ?int $imageIndex = null,
        public float $sourceWidthPt = 0.0,
        public float $sourceHeightPt = 0.0,
        public ?Border $frameBorder = null,
        public ?Color $frameBackground = null,
        public ?int $importIndex = null,
        public ?ImportedPage $importPage = null,
        /** @var list<SvgShape>|null At the source's intrinsic size; the renderer scales to fit. */
        public ?array $vectorShapes = null,
    ) {
    }

    public static function forBlocks(Rect $rect, BoxAlign $align, StackBox $blocks, bool $geometricShrink = true): self
    {
        return new self(
            $rect,
            Fit::Contain,
            $align,
            blocks: $blocks,
            blocksNaturalHeightPt: $blocks->contentHeightPt(),
            blocksGeometricShrink: $geometricShrink,
        );
    }

    public static function forImage(
        Rect $rect,
        Fit $fit,
        BoxAlign $align,
        int $imageIndex,
        float $sourceWidthPt,
        float $sourceHeightPt,
    ): self {
        return new self(
            $rect,
            $fit,
            $align,
            imageIndex: $imageIndex,
            sourceWidthPt: $sourceWidthPt,
            sourceHeightPt: $sourceHeightPt,
        );
    }

    /**
     * Vector linework from an SVG. The shapes arrive at intrinsic size, so the
     * renderer resolves the fit exactly as it does for a raster image.
     *
     * @param list<SvgShape> $shapes
     */
    public static function forVector(
        Rect $rect,
        Fit $fit,
        BoxAlign $align,
        array $shapes,
        float $sourceWidthPt,
        float $sourceHeightPt,
    ): self {
        return new self(
            $rect,
            $fit,
            $align,
            sourceWidthPt: $sourceWidthPt,
            sourceHeightPt: $sourceHeightPt,
            vectorShapes: $shapes,
        );
    }

    public static function forFrame(Rect $rect, Border $border, ?Color $background): self
    {
        return new self($rect, Fit::Contain, BoxAlign::Center, frameBorder: $border, frameBackground: $background);
    }

    public static function forImport(
        Rect $rect,
        Fit $fit,
        BoxAlign $align,
        int $importIndex,
        ImportedPage $page,
    ): self {
        return new self(
            $rect,
            $fit,
            $align,
            sourceWidthPt: $page->widthPt(),
            sourceHeightPt: $page->heightPt(),
            importIndex: $importIndex,
            importPage: $page,
        );
    }
}
