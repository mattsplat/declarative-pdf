<?php

declare(strict_types=1);

namespace Pdf\Tests\Support;

use Pdf\Layout\Box;
use Pdf\Layout\Canvas;

/**
 * A minimal {@see Box} for exercising {@see \Pdf\Layout\Box\StackBox}.
 *
 * `splittable` boxes divide their height arbitrarily; non-splittable ones move
 * whole. Rendering records the y it was drawn at.
 */
final class FakeBox implements Box
{
    /** @var list<float> */
    public array $renderedAt = [];

    public function __construct(
        public readonly string $label,
        private readonly float $height,
        private readonly float $marginBefore = 0.0,
        private readonly float $marginAfter = 0.0,
        private readonly bool $keepWithNext = false,
        private readonly bool $keepTogether = false,
        private readonly bool $splittable = false,
    ) {
    }

    public function contentHeightPt(): float
    {
        return $this->height;
    }

    public function marginBeforePt(): float
    {
        return $this->marginBefore;
    }

    public function marginAfterPt(): float
    {
        return $this->marginAfter;
    }

    public function keepWithNext(): bool
    {
        return $this->keepWithNext;
    }

    public function keepTogether(): bool
    {
        return $this->keepTogether;
    }

    public function hasForcedBreak(): bool
    {
        return false;
    }

    public function split(float $availableHeightPt): array
    {
        if ($this->height <= $availableHeightPt + 1e-4) {
            return [$this, null];
        }
        if (!$this->splittable || $availableHeightPt <= 1e-4) {
            return [null, $this];
        }

        return [
            new self($this->label . '#head', $availableHeightPt, $this->marginBefore, 0.0, false, false, true),
            new self($this->label . '#tail', $this->height - $availableHeightPt, 0.0, $this->marginAfter, $this->keepWithNext, false, true),
        ];
    }

    public function render(Canvas $canvas, float $xPt, float $yTopPt, float $widthPt): void
    {
        $this->renderedAt[] = $yTopPt;
    }

    public function minIntrinsicWidthPt(): float
    {
        return 0.0;
    }

    public function maxIntrinsicWidthPt(): float
    {
        return 0.0;
    }
}
