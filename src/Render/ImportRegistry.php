<?php

declare(strict_types=1);

namespace Pdf\Render;

use Pdf\Import\PdfImportDocument;

/**
 * Interns the external PDF pages a document places, parsing each source file
 * once.
 */
final class ImportRegistry
{
    /** @var array<string, PdfImportDocument> */
    private array $documents = [];

    /** @var array<string, ResolvedImport> */
    private array $used = [];

    public function use(string $path, int $page): ResolvedImport
    {
        $key = $path . "\0" . $page;
        if (isset($this->used[$key])) {
            return $this->used[$key];
        }

        $document = $this->documents[$path] ??= PdfImportDocument::fromFile($path);

        return $this->used[$key] = new ResolvedImport(count($this->used) + 1, $document->page($page));
    }

    /** @return list<ResolvedImport> */
    public function used(): array
    {
        return array_values($this->used);
    }

    public function isEmpty(): bool
    {
        return $this->used === [];
    }
}
