<?php

declare(strict_types=1);

namespace Pdf\Node;

use Pdf\Style\StylePatch;

/**
 * A single on/off checkbox (`/FT /Btn`).
 *
 * `exportValue` is the name of the "on" state written to `/V` and to the FDF on
 * submit (defaults to `Yes`); the "off" state is always `/Off`. The control is
 * square: with no explicit size it is a box the height of one line of text, and
 * an optional `label` is drawn to its right.
 */
final readonly class Checkbox implements FormField
{
    public function __construct(
        public string $name,
        public bool $checked = false,
        public string $exportValue = 'Yes',
        public ?string $label = null,
        public ?float $sizePt = null,
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
