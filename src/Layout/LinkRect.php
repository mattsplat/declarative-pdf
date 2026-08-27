<?php

declare(strict_types=1);

namespace Pdf\Layout;

/**
 * A clickable region recorded during rendering, in top-left user space.
 *
 * `$target` is either a URI or an `#name` reference to a {@see \Pdf\Node\Anchor}.
 */
final readonly class LinkRect
{
    public function __construct(
        public float $xPt,
        public float $yTopPt,
        public float $widthPt,
        public float $heightPt,
        public string $target,
    ) {
    }

    public function isInternal(): bool
    {
        return str_starts_with($this->target, '#');
    }

    public function anchorName(): string
    {
        return ltrim($this->target, '#');
    }
}
