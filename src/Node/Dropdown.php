<?php

declare(strict_types=1);

namespace Pdf\Node;

use Pdf\Style\StylePatch;

/**
 * A combo box (`/FT /Ch` with the Combo flag): a drop-down list of choices,
 * optionally with a free-text entry field (`editable`).
 *
 * `options` are `value => label` pairs; a list of bare strings is used as both.
 */
final readonly class Dropdown implements FormField
{
    /** @var list<array{export: string, label: string}> */
    public array $options;

    /**
     * @param iterable<string, string>|iterable<int, string> $options
     */
    public function __construct(
        public string $name,
        iterable $options,
        public string $value = '',
        public ?string $label = null,
        public ?float $widthPt = null,
        public ?float $heightPt = null,
        public bool $editable = false,
        public bool $sort = false,
        public bool $required = false,
        public bool $readOnly = false,
        public ?string $tooltip = null,
        private StylePatch $patch = new StylePatch(),
    ) {
        $this->options = ChoiceOptions::normalise($options);
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
