<?php

declare(strict_types=1);

namespace Pdf\Node\Placement;

/**
 * An SVG placed into an area with a fit mode, drawn as vector linework — the
 * {@see Picture} of the vector world.
 */
final readonly class Vector implements PlacementContent
{
    public function __construct(public string $source)
    {
    }
}
