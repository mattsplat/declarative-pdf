<?php

declare(strict_types=1);

namespace Pdf\Node\Placement;

/**
 * A raster image (JPEG/PNG/GIF/WebP) placed into an area with a fit mode.
 */
final readonly class Picture implements PlacementContent
{
    public function __construct(public string $path)
    {
    }
}
