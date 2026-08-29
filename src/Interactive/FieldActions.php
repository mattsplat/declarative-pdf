<?php

declare(strict_types=1);

namespace Pdf\Interactive;

/**
 * The JavaScript actions attached to one field: the `/AA` additional-actions
 * entries (`/K` keystroke, `/F` format, `/V` validate, `/C` calculate) and, for
 * a push button, the primary `/A` click action.
 *
 * A field with a `calculate` action is added to `/AcroForm /CO`.
 */
final readonly class FieldActions
{
    public function __construct(
        public ?Js $keystroke = null,
        public ?Js $format = null,
        public ?Js $validate = null,
        public ?Js $calculate = null,
        public ?Js $click = null,
    ) {
    }

    public function isEmpty(): bool
    {
        return $this->keystroke === null
            && $this->format === null
            && $this->validate === null
            && $this->calculate === null
            && $this->click === null;
    }

    public function hasCalculate(): bool
    {
        return $this->calculate !== null;
    }

    /**
     * The `/AA` sub-dictionary entries, keyed by their PDF name (`K`, `F`, `V`,
     * `C`), each value the JavaScript source.
     *
     * @return array<string, string>
     */
    public function additionalActions(): array
    {
        $entries = [];
        if ($this->keystroke !== null) {
            $entries['K'] = $this->keystroke->source;
        }
        if ($this->format !== null) {
            $entries['F'] = $this->format->source;
        }
        if ($this->validate !== null) {
            $entries['V'] = $this->validate->source;
        }
        if ($this->calculate !== null) {
            $entries['C'] = $this->calculate->source;
        }

        return $entries;
    }
}
