<?php

declare(strict_types=1);

namespace Pdf\Font;

/**
 * A font that has been resolved and assigned a resource index (`/F{index}`).
 */
final class ResolvedFont
{
    public ?int $objectNumber = null;

    public function __construct(
        public readonly int $index,
        public readonly FontDefinition $definition,
        public readonly FontMetrics $metrics,
    ) {
    }
}
