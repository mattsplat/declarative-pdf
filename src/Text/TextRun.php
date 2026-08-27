<?php

declare(strict_types=1);

namespace Pdf\Text;

use Pdf\Style\StylePatch;

/**
 * A run of inline content: either a stretch of styled text (optionally a link),
 * or — when `imagePath` is set — an inline image.
 */
final readonly class TextRun
{
    public function __construct(
        public string $text,
        public StylePatch $patch = new StylePatch(),
        public ?string $link = null,
        public ?string $imagePath = null,
        public ?float $imageWidthPt = null,
        public ?float $imageHeightPt = null,
    ) {
    }

    public function isImage(): bool
    {
        return $this->imagePath !== null;
    }
}
