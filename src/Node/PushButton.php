<?php

declare(strict_types=1);

namespace Pdf\Node;

use Pdf\Interactive\ButtonKind;
use Pdf\Interactive\SubmitFormat;
use Pdf\Style\StylePatch;

/**
 * A clickable button (`/FT /Btn` with the Pushbutton flag). It holds no value.
 *
 * `ButtonKind::Submit` and `ButtonKind::Reset` emit the native `/SubmitForm` /
 * `/ResetForm` actions, which work in Acrobat, Foxit and most desktop viewers
 * without JavaScript. `ButtonKind::Push` is an inert button until a JavaScript
 * `/A` action is attached (see the JavaScript layer).
 */
final readonly class PushButton implements FormField
{
    public function __construct(
        public string $name,
        public string $label,
        public ButtonKind $kind = ButtonKind::Push,
        public ?string $submitUrl = null,
        public SubmitFormat $submitFormat = SubmitFormat::Fdf,
        public ?float $widthPt = null,
        public ?float $heightPt = null,
        public ?string $tooltip = null,
        public bool $readOnly = false,
        private StylePatch $patch = new StylePatch(),
    ) {
    }

    public static function submit(
        string $name,
        string $label,
        string $url,
        SubmitFormat $format = SubmitFormat::Fdf,
        StylePatch $patch = new StylePatch(),
    ): self {
        return new self($name, $label, ButtonKind::Submit, $url, $format, patch: $patch);
    }

    public static function reset(string $name, string $label, StylePatch $patch = new StylePatch()): self
    {
        return new self($name, $label, ButtonKind::Reset, patch: $patch);
    }

    public function fieldName(): string
    {
        return $this->name;
    }

    public function fieldLabel(): ?string
    {
        return null;
    }

    public function isRequired(): bool
    {
        return false;
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
