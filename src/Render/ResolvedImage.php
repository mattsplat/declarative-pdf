<?php

declare(strict_types=1);

namespace Pdf\Render;

use Pdf\Image\ImageResource;

/**
 * A resolved image with a resource index (`/I{index}`) and, once written, its
 * PDF object number.
 */
final class ResolvedImage
{
    public ?int $objectNumber = null;

    public function __construct(
        public readonly int $index,
        public readonly ImageResource $resource,
    ) {
    }
}
