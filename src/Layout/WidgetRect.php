<?php

declare(strict_types=1);

namespace Pdf\Layout;

use Pdf\Interactive\FieldSpec;

/**
 * A form-field widget annotation recorded while a {@see \Pdf\Layout\Box\FieldBox}
 * renders, in top-left user space — the counterpart of {@see LinkRect} for
 * `/Subtype /Widget`.
 *
 * A checkbox, text field, choice or button records exactly one; a radio group
 * records one per option, each carrying that option's export value.
 */
final readonly class WidgetRect
{
    public function __construct(
        public float $xPt,
        public float $yTopPt,
        public float $widthPt,
        public float $heightPt,
        public FieldSpec $spec,
        public ?string $optionExport = null,
        public bool $optionSelected = false,
    ) {
    }

    /** Re-project onto a parent page after an absolute placement scales the sub-stream. */
    public function scaled(float $scale, float $dxPt, float $dyPt): self
    {
        return new self(
            $dxPt + $scale * $this->xPt,
            $dyPt + $scale * $this->yTopPt,
            $scale * $this->widthPt,
            $scale * $this->heightPt,
            $this->spec,
            $this->optionExport,
            $this->optionSelected,
        );
    }
}
