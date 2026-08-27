<?php

declare(strict_types=1);

namespace Pdf\Import;

/**
 * A parsed PDF dictionary. Kept distinct from a plain array so serialisation
 * back out stays faithful.
 */
final readonly class PdfDictionary
{
    /** @param array<string, mixed> $entries */
    public function __construct(public array $entries)
    {
    }

    public function get(string $key): mixed
    {
        return $this->entries[$key] ?? null;
    }

    public function has(string $key): bool
    {
        return array_key_exists($key, $this->entries);
    }
}
