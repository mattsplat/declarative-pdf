<?php

declare(strict_types=1);

namespace Pdf\Render;

/**
 * Hands out unique, deterministic resource names (`Sh1`, `Sh2`, …) shared by
 * every {@see ContentStream} of a single render, so a name minted on one page
 * cannot collide with one on another in the shared resource dictionary.
 */
final class ResourceNamer
{
    /** @var array<string, int> */
    private array $counters = [];

    public function next(string $prefix): string
    {
        $this->counters[$prefix] = ($this->counters[$prefix] ?? 0) + 1;

        return $prefix . $this->counters[$prefix];
    }
}
