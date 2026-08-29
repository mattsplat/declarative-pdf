<?php

declare(strict_types=1);

namespace Pdf\Node;

use Pdf\Style\StylePatch;

/**
 * A scrollable list box (`/FT /Ch`, no Combo flag). `multiSelect` allows more
 * than one row to be chosen.
 *
 * `options` are `value => label` pairs; a list of bare strings is used as both.
 * `selected` is the export value(s) initially highlighted.
 */
final readonly class ListBox implements FormField
{
    /** @var list<array{export: string, label: string}> */
    public array $options;

    /** @var list<string> */
    public array $selected;

    /**
     * @param iterable<int|string, string> $options
     * @param list<string>|string          $selected
     */
    public function __construct(
        public string $name,
        iterable $options,
        array|string $selected = [],
        public ?string $label = null,
        public ?float $widthPt = null,
        public ?float $heightPt = null,
        public bool $multiSelect = false,
        public bool $sort = false,
        public bool $required = false,
        public bool $readOnly = false,
        public ?string $tooltip = null,
        private StylePatch $patch = new StylePatch(),
    ) {
        $this->options = ChoiceOptions::normalise($options);
        $this->selected = is_string($selected) ? ($selected === '' ? [] : [$selected]) : $selected;
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
