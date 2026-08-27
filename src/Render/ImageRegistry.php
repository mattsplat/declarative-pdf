<?php

declare(strict_types=1);

namespace Pdf\Render;

use Pdf\Image\ImageResource;

/**
 * Interns the images a document uses. Ports the runtime half of FPDF's
 * `$this->images` array (the `i` index added in `Image()`, fpdf.php:890).
 */
final class ImageRegistry
{
    /** @var array<string, ResolvedImage> */
    private array $used = [];

    public function use(ImageResource $resource): ResolvedImage
    {
        return $this->used[$resource->cacheKey] ??= new ResolvedImage(
            index: count($this->used) + 1,
            resource: $resource,
        );
    }

    /** @return list<ResolvedImage> */
    public function used(): array
    {
        return array_values($this->used);
    }

    public function isEmpty(): bool
    {
        return $this->used === [];
    }

    public function hasAlpha(): bool
    {
        foreach ($this->used as $image) {
            if ($image->resource->hasAlpha()) {
                return true;
            }
        }

        return false;
    }

    public function requiresPdf14(): bool
    {
        foreach ($this->used as $image) {
            if ($image->resource->requiresPdf14) {
                return true;
            }
        }

        return false;
    }
}
