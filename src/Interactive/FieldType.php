<?php

declare(strict_types=1);

namespace Pdf\Interactive;

/**
 * The seven interactive field kinds. Several map to the same PDF `/FT` value:
 * `Checkbox`, `Radio` and `PushButton` are all `/Btn` in the file and differ
 * only by their field flags and widget layout.
 */
enum FieldType
{
    case Text;
    case Checkbox;
    case Radio;
    case Dropdown;
    case ListBox;
    case PushButton;
    case Signature;

    /** The `/FT` name written into the field dictionary. */
    public function acroName(): string
    {
        return match ($this) {
            self::Text => 'Tx',
            self::Checkbox, self::Radio, self::PushButton => 'Btn',
            self::Dropdown, self::ListBox => 'Ch',
            self::Signature => 'Sig',
        };
    }
}
