<?php

declare(strict_types=1);

namespace Pdf\Interactive;

/**
 * A fully-resolved, render-agnostic description of one form field, produced by
 * the {@see \Pdf\Layout\Measurer} from a form node and consumed by
 * {@see \Pdf\Render\AcroFormWriter}.
 *
 * One `FieldSpec` corresponds to one entry in `/AcroForm /Fields`. A checkbox,
 * text field, choice or button owns exactly one widget; a radio group owns one
 * widget per option, all sharing this spec and distinguished by their export
 * value.
 */
final readonly class FieldSpec
{
    /**
     * @param list<array{export: string, label: string}> $options choice items / radio options, in author order
     */
    public function __construct(
        public FieldType $type,
        public string $fullName,
        public int $flags,
        public FieldAppearance $appearance,
        public ?string $tooltip = null,
        public ?string $value = null,
        public ?string $defaultValue = null,
        public ?int $maxLength = null,
        public array $options = [],
        public string $onState = 'Yes',
        public ?string $buttonLabel = null,
        public ButtonKind $buttonKind = ButtonKind::Push,
        public ?string $submitUrl = null,
        public SubmitFormat $submitFormat = SubmitFormat::Fdf,
        public FieldActions $actions = new FieldActions(),
    ) {
    }

    public function isFlagSet(int $flag): bool
    {
        return ($this->flags & $flag) !== 0;
    }

    /** A comb text field paints one cell per `maxLength` character. */
    public function isComb(): bool
    {
        return $this->type === FieldType::Text
            && $this->maxLength !== null
            && $this->maxLength > 0
            && $this->isFlagSet(FieldFlag::COMB);
    }
}
