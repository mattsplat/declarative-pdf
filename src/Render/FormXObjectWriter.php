<?php

declare(strict_types=1);

namespace Pdf\Render;

use Pdf\Import\PdfStream;

/**
 * Emits an imported PDF page as a Form XObject, copying its resource
 * dependencies with renumbered object references.
 */
final class FormXObjectWriter
{
    public function __construct(private readonly PdfWriter $writer)
    {
    }

    /** @param list<ResolvedImport> $imports */
    public function write(array $imports): void
    {
        foreach ($imports as $import) {
            $import->objectNumber = $this->writeOne($import);
        }
    }

    private function writeOne(ResolvedImport $import): int
    {
        $page = $import->page;
        $registry = $this->writer->registry();

        /** @var array<int, int> $map old object number => new object number */
        $map = [];
        foreach (array_keys($page->dependencies) as $oldNumber) {
            $map[$oldNumber] = $registry->allocate();
        }

        foreach ($page->dependencies as $oldNumber => $value) {
            $this->writer->beginObject($map[$oldNumber]);
            if ($value instanceof PdfStream) {
                $this->writer->line(PdfValueWriter::dictionary(
                    [...$value->dict, 'Length' => strlen($value->rawData)],
                    $map,
                ));
                $this->writer->stream($value->rawData);
            } else {
                $this->writer->line(PdfValueWriter::write($value, $map));
            }
            $this->writer->endObject();
        }

        $formObject = $this->writer->beginObject();
        $box = $page->boundingBox;
        $this->writer->line(sprintf(
            '<</Type /XObject /Subtype /Form /FormType 1 /BBox [%.4F %.4F %.4F %.4F] /Resources %s /Length %d>>',
            $box[0],
            $box[1],
            $box[2],
            $box[3],
            PdfValueWriter::write($page->resources, $map),
            strlen($page->contentBytes),
        ));
        $this->writer->stream($page->contentBytes);
        $this->writer->endObject();

        return $formObject;
    }
}
