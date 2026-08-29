<?php

declare(strict_types=1);

namespace Pdf\Interactive;

/**
 * The `/Ff` field-flag bits (PDF 32000-1:2008, tables 227-230).
 *
 * These are combined with `|` into a single integer, so they are class
 * constants rather than an enum: a resolved field carries one `/Ff` value, not
 * a member of a closed set.
 */
final class FieldFlag
{
    // Common to every field type.
    public const int READ_ONLY = 1 << 0;
    public const int REQUIRED = 1 << 1;
    public const int NO_EXPORT = 1 << 2;

    // Text fields (`/Tx`).
    public const int MULTILINE = 1 << 12;
    public const int PASSWORD = 1 << 13;
    public const int DO_NOT_SPELL_CHECK = 1 << 22;
    public const int DO_NOT_SCROLL = 1 << 23;
    public const int COMB = 1 << 24;

    // Buttons (`/Btn`).
    public const int NO_TOGGLE_TO_OFF = 1 << 14;
    public const int RADIO = 1 << 15;
    public const int PUSHBUTTON = 1 << 16;
    public const int RADIOS_IN_UNISON = 1 << 25;

    // Choice fields (`/Ch`).
    public const int COMBO = 1 << 17;
    public const int EDIT = 1 << 18;
    public const int SORT = 1 << 19;
    public const int MULTI_SELECT = 1 << 21;
    public const int COMMIT_ON_SEL_CHANGE = 1 << 26;

    private function __construct()
    {
    }
}
