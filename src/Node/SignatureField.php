<?php

declare(strict_types=1);

namespace Pdf\Node;

use Pdf\Style\StylePatch;

/**
 * An empty signature field (`/FT /Sig`): a placeholder the recipient signs
 * later in Acrobat, Foxit or another signing-capable viewer. This library only
 * emits the widget and the `/SigFlags` entry — it does not create the
 * cryptographic signature.
 */
final readonly class SignatureField implements FormField
{
    public function __construct(
        public string $name,
        public ?string $label = null,
        public ?float $widthPt = null,
        public ?float $heightPt = null,
        public bool $required = false,
        public bool $readOnly = false,
        public ?string $tooltip = null,
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
