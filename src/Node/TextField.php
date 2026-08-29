<?php

declare(strict_types=1);

namespace Pdf\Node;

use Pdf\Style\StylePatch;
use Pdf\Style\TextAlign;

/**
 * A single- or multi-line text entry field (`/FT /Tx`).
 *
 * With no `heightPt` a single-line field is sized to its font; a `multiline`
 * field defaults to three lines. `comb` needs a `maxLength` and paints one cell
 * per character. `password` masks input in the viewer (the self-drawn
 * appearance still shows the value, so do not pre-fill a real secret).
 */
final readonly class TextField implements FormField
{
    public function __construct(
        public string $name,
        public string $value = '',
        public ?string $label = null,
        public ?float $widthPt = null,
        public ?float $heightPt = null,
        public bool $multiline = false,
        public ?int $maxLength = null,
        public bool $comb = false,
        public bool $password = false,
        public bool $required = false,
        public bool $readOnly = false,
        public ?string $tooltip = null,
        public TextAlign $align = TextAlign::Left,
        public float $fontSizePt = 0.0,
        private StylePatch $patch = new StylePatch(),
    ) {
    }

    public function fieldName(): string
    {
        return $this->name;
    }

    public function fieldLabel(): ?string
    {
        return $this->label;
    }

    public function isRequired(): bool
    {
        return $this->required;
    }

    public function isReadOnly(): bool
    {
        return $this->readOnly;
    }

    public function patch(): StylePatch
    {
        return $this->patch;
    }
}
