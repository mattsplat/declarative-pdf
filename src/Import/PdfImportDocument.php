<?php

declare(strict_types=1);

namespace Pdf\Import;

use Pdf\Exception\PdfException;

/**
 * Navigates an imported PDF's page tree and builds an {@see ImportedPage}.
 */
final class PdfImportDocument
{
    private const INHERITABLE = ['Resources', 'MediaBox', 'CropBox', 'Rotate'];

    /** @var list<array{dict: PdfDictionary, inherited: array<string, mixed>}> */
    private array $pages = [];

    /** @var array<int, ImportedPage> */
    private array $built = [];

    public function __construct(private readonly PdfReader $reader)
    {
        $this->collectPages();
    }

    public static function fromFile(string $path): self
    {
        return new self(PdfReader::fromFile($path));
    }

    public function pageCount(): int
    {
        return count($this->pages);
    }

    public function page(int $oneBasedIndex): ImportedPage
    {
        if ($oneBasedIndex < 1 || $oneBasedIndex > count($this->pages)) {
            throw new PdfException(sprintf('PDF has %d pages; page %d requested.', count($this->pages), $oneBasedIndex));
        }

        return $this->built[$oneBasedIndex] ??= $this->build($this->pages[$oneBasedIndex - 1]);
    }

    private function collectPages(): void
    {
        $root = $this->reader->resolve($this->reader->trailer()->get('Root'));
        if (!$root instanceof PdfDictionary) {
            throw new PdfException('PDF catalog is missing.');
        }
        $pagesRoot = $this->reader->resolve($root->get('Pages'));
        if (!$pagesRoot instanceof PdfDictionary) {
            throw new PdfException('PDF page tree root is missing.');
        }

        $this->walk($pagesRoot, [], 0);

        if ($this->pages === []) {
            throw new PdfException('PDF has no pages.');
        }
    }

    /** @param array<string, mixed> $inherited */
    private function walk(PdfDictionary $node, array $inherited, int $depth): void
    {
        if ($depth > 64) {
            throw new PdfException('PDF page tree is too deep (possible loop).');
        }

        foreach (self::INHERITABLE as $key) {
            if ($node->has($key)) {
                $inherited[$key] = $node->get($key);
            }
        }

        $type = $this->reader->resolve($node->get('Type'));
        $typeName = $type instanceof PdfName ? $type->value : null;

        if ($typeName === 'Pages' || $node->has('Kids')) {
            $kids = $this->reader->resolve($node->get('Kids'));
            if (!is_array($kids)) {
                throw new PdfException('PDF page tree node has an unreadable /Kids array.');
            }
            foreach ($kids as $kid) {
                $child = $this->reader->resolve($kid);
                if (!$child instanceof PdfDictionary) {
                    throw new PdfException('PDF page tree contains an unresolvable kid.');
                }
                $this->walk($child, $inherited, $depth + 1);
            }

            return;
        }

        if ($typeName === 'Page' || $node->has('Contents') || $node->has('MediaBox')) {
            $this->pages[] = ['dict' => $node, 'inherited' => $inherited];

            return;
        }

        throw new PdfException('Unrecognised node in the PDF page tree.');
    }

    /** @param array{dict: PdfDictionary, inherited: array<string, mixed>} $entry */
    private function build(array $entry): ImportedPage
    {
        $dict = $entry['dict'];
        $get = fn (string $key): mixed => $this->reader->resolve(
            $dict->has($key) ? $dict->get($key) : ($entry['inherited'][$key] ?? null),
        );

        $resources = $get('Resources');
        if (!$resources instanceof PdfDictionary) {
            $resources = new PdfDictionary([]);
        }

        $media = $this->rectangle($get('MediaBox')) ?? [0.0, 0.0, 612.0, 792.0];
        $crop = $this->rectangle($get('CropBox')) ?? $media;
        $box = [
            min($crop[0], $crop[2]),
            min($crop[1], $crop[3]),
            max($crop[0], $crop[2]),
            max($crop[1], $crop[3]),
        ];

        $rotate = $get('Rotate');
        $rotation = is_int($rotate) ? ((($rotate % 360) + 360) % 360) : 0;

        // Dependencies: transitive closure of everything the resources reference.
        /** @var array<int, mixed> $dependencies */
        $dependencies = [];
        $this->collectRefs($resources, $dependencies, 0);

        return new ImportedPage(
            contentBytes: $this->contentBytes($get('Contents')),
            resources: $resources,
            boundingBox: [$box[0], $box[1], $box[2], $box[3]],
            rotation: $rotation,
            dependencies: $dependencies,
        );
    }

    /** @return array{0: float, 1: float, 2: float, 3: float}|null */
    private function rectangle(mixed $value): ?array
    {
        if (!is_array($value) || count($value) !== 4) {
            return null;
        }
        $nums = array_map(fn ($v) => (float) $this->reader->resolve($v), $value);

        return [$nums[0], $nums[1], $nums[2], $nums[3]];
    }

    private function contentBytes(mixed $contents): string
    {
        $streams = is_array($contents) ? $contents : [$contents];
        $parts = [];
        foreach ($streams as $ref) {
            $stream = $this->reader->resolve($ref);
            if ($stream instanceof PdfStream) {
                $parts[] = $stream->decoded();
            }
        }

        return implode("\n", $parts);
    }

    /** @param array<int, mixed> $out */
    private function collectRefs(mixed $value, array &$out, int $depth): void
    {
        if ($depth > 256) {
            return;
        }

        if ($value instanceof PdfReference) {
            if (array_key_exists($value->number, $out)) {
                return;
            }
            $resolved = $this->reader->object($value->number);
            $out[$value->number] = $resolved;
            $this->collectRefs($resolved, $out, $depth + 1);

            return;
        }

        if ($value instanceof PdfDictionary) {
            foreach ($value->entries as $entry) {
                $this->collectRefs($entry, $out, $depth + 1);
            }

            return;
        }

        if ($value instanceof PdfStream) {
            foreach ($value->dict as $entry) {
                $this->collectRefs($entry, $out, $depth + 1);
            }

            return;
        }

        if (is_array($value)) {
            foreach ($value as $item) {
                $this->collectRefs($item, $out, $depth + 1);
            }
        }
    }
}
