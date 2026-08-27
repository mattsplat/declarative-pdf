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
        public ?int $imageIndex = null,
        public float $sourceWidthPt = 0.0,
        public float $sourceHeightPt = 0.0,
        public ?Border $frameBorder = null,
        public ?Color $frameBackground = null,
        public ?int $importIndex = null,
        public ?ImportedPage $importPage = null,
    ) {
    }

    public static function forBlocks(Rect $rect, BoxAlign $align, StackBox $blocks): self
    {
        return new self($rect, Fit::Contain, $align, blocks: $blocks, blocksNaturalHeightPt: $blocks->contentHeightPt());
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
