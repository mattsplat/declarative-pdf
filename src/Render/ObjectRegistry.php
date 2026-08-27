<?php

declare(strict_types=1);

namespace Pdf\Render;

/**
 * Allocates PDF object numbers and records their byte offsets for the xref
 * table.
 *
 * Ports the `$n` / `$offsets` bookkeeping around `_newobj()` (fpdf.php:1531)
 * and `_getoffset()` (fpdf.php:1526). FPDF reserves object 1 for the page tree
 * and object 2 for the shared resource dictionary, so numbering starts at 2.
 */
final class ObjectRegistry
{
    private int $next;

    /** @var array<int, int> object number => byte offset */
    private array $offsets = [];

    public function __construct(int $reserved = 2)
    {
        $this->next = $reserved;
    }

    public function allocate(): int
    {
        return ++$this->next;
    }

    public function current(): int
    {
        return $this->next;
    }

    public function recordOffset(int $object, int $offset): void
    {
        $this->offsets[$object] = $offset;
    }

    /** @return array<int, int> */
    public function offsets(): array
    {
        return $this->offsets;
    }
}
