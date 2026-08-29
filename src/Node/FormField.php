<?php

declare(strict_types=1);

namespace Pdf\Node;

/**
 * Marker for the interactive form-field nodes ({@see TextField},
 * {@see Checkbox}, {@see RadioGroup}, {@see Dropdown}, {@see ListBox},
 * {@see PushButton}, {@see SignatureField}).
 *
 * Each becomes one entry in the document's `/AcroForm /Fields` array and one or
 * more `/Widget` annotations on the page it lands on. The {@see \Pdf\Layout\Measurer}
 * dispatches on the concrete class; this interface only lets the flow engine
 * recognise a field generically.
 */
interface FormField extends BlockNode
{
    /** The `/T` field name — unique within the document. */
    public function fieldName(): string;

    /** Text drawn next to the control, or null for none. */
    public function fieldLabel(): ?string;

    public function isRequired(): bool;

    public function isReadOnly(): bool;
}
