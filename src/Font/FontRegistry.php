<?php

declare(strict_types=1);

namespace Pdf\Font;

/**
 * Interns the fonts actually used by a document and assigns each a resource
 * index.
 *
 * Ports the runtime half of FPDF's `$this->fonts` array (the `i` index added
 * in `AddFont()`, fpdf.php:465) while font *definitions* come from
 * {@see FontRepository}.
 */
final class FontRegistry
{
    /** @var array<string, ResolvedFont> */
    private array $used = [];

    public function __construct(private readonly FontRepository $repository)
    {
    }

    /**
     * Interned on the *resolved* definition, not the request: two weights that
     * fall back to the same file share one `/F{index}` rather than embedding
     * the font program twice.
     */
    public function use(string $family, FontFace $face): ResolvedFont
    {
        $definition = $this->repository->resolve($family, $face);

        return $this->used[$definition->identity()] ??= new ResolvedFont(
            index: count($this->used) + 1,
            definition: $definition,
            metrics: $definition->metrics(),
        );
    }

    /** @return list<ResolvedFont> */
    public function used(): array
    {
        return array_values($this->used);
    }
}
