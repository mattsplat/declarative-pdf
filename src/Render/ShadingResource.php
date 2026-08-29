<?php

declare(strict_types=1);

namespace Pdf\Render;

/**
 * A `/Shading` dictionary a {@see ContentStream} referenced with `sh`, waiting
 * to be written as an indirect object and listed in the page resource dict.
 *
 * The dictionary body is fully resolved when it is built — its `/Coords` are
 * already in final page space — so the writer only has to wrap it in an object.
 */
final class ShadingResource
{
    public int $objectNumber = 0;

    public function __construct(
        public readonly string $name,
        public readonly string $dictionary,
    ) {
    }
}
