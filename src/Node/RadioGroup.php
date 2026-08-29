<?php

declare(strict_types=1);

namespace Pdf\Node;

use Pdf\Style\StylePatch;

/**
 * A set of mutually-exclusive radio buttons sharing one field name
 * (`/FT /Btn` with the Radio flag). Each {@see RadioOption} becomes a widget
 * annotation whose "on" state is that option's export value; picking one writes
 * that value to the group's `/V`.
 *
 * The options stack vertically, one per row; `rowHeightPt` is the height of a
 * row and the diameter of the button.
 */
final readonly class RadioGroup implements FormField
{
    /** @var list<RadioOption> */
    public array $options;

    /**
     * @param iterable<int|string, RadioOption|string> $options a `RadioOption`, a bare
     *   string (export === label), or a `string $export => string $label` pair
     */
    public function __construct(
        public string $name,
        iterable $options,
        public string $value = '',
        public ?string $label = null,
        public float $rowHeightPt = 16.0,
        public float $rowGapPt = 4.0,
        public bool $required = false,
        public bool $readOnly = false,
        public ?string $tooltip = null,
        private StylePatch $patch = new StylePatch(),
    ) {
        $normalised = [];
        foreach ($options as $key => $option) {
            $normalised[] = match (true) {
                $option instanceof RadioOption => $option,
                is_string($key) => new RadioOption($key, $option),
                default => RadioOption::of($option),
            };
        }
        $this->options = $normalised;
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
