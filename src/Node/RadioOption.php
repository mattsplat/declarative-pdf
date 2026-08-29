<?php

declare(strict_types=1);

namespace Pdf\Node;

/**
 * One choice within a {@see RadioGroup}: the `export` value written to `/V`
 * when it is picked, and the `label` drawn next to the button.
 */
final readonly class RadioOption
{
    public function __construct(
        public string $export,
        public string $label,
    ) {
    }

    public static function of(string $export, ?string $label = null): self
    {
        return new self($export, $label ?? $export);
    }
}
