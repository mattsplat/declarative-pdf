<?php

declare(strict_types=1);

namespace Pdf\Import;

/**
 * One page extracted from an external PDF, ready to be re-emitted as a Form
 * XObject: its decoded content, its (resolved) resource dictionary, the
 * transitive closure of objects those resources reference, its visible box and
 * its rotation.
 */
final readonly class ImportedPage
{
    /**
     * @param array{0: float, 1: float, 2: float, 3: float} $boundingBox llx, lly, urx, ury
     * @param array<int, mixed>                             $dependencies objNum => parsed object
     */
    public function __construct(
        public string $contentBytes,
        public PdfDictionary $resources,
        public array $boundingBox,
        public int $rotation,
        public array $dependencies,
    ) {
    }

    public function boxWidthPt(): float
    {
        return abs($this->boundingBox[2] - $this->boundingBox[0]);
    }

    public function boxHeightPt(): float
    {
        return abs($this->boundingBox[3] - $this->boundingBox[1]);
    }

    /** Visible width after the page's own rotation. */
    public function widthPt(): float
    {
        return $this->rotation % 180 === 0 ? $this->boxWidthPt() : $this->boxHeightPt();
    }

    /** Visible height after the page's own rotation. */
    public function heightPt(): float
    {
        return $this->rotation % 180 === 0 ? $this->boxHeightPt() : $this->boxWidthPt();
    }
}
