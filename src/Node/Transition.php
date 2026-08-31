<?php

declare(strict_types=1);

namespace Pdf\Node;

/**
 * A page-to-page transition effect — the PDF `/Trans` dictionary a viewer plays
 * when advancing to a page in full-screen (presentation) mode.
 *
 * Build one with a named constructor; each takes only the parameters its style
 * uses and {@see self::dictionary()} emits only the corresponding keys, so the
 * `/Trans` dict is always spec-shaped (PDF 2.0, Table 168).
 *
 *   $master = $master->withTransition(Transition::wipe(TransitionDirection::Leftward, 0.5));
 */
final readonly class Transition
{
    private function __construct(
        public TransitionStyle $style,
        public float $durationSec = 1.0,
        public ?TransitionAxis $axis = null,
        public ?TransitionDirection $direction = null,
        public ?TransitionMotion $motion = null,
    ) {
    }

    public static function split(
        TransitionAxis $axis = TransitionAxis::Horizontal,
        TransitionMotion $motion = TransitionMotion::In,
        float $durationSec = 1.0,
    ): self {
        return new self(TransitionStyle::Split, $durationSec, axis: $axis, motion: $motion);
    }

    public static function blinds(
        TransitionAxis $axis = TransitionAxis::Horizontal,
        float $durationSec = 1.0,
    ): self {
        return new self(TransitionStyle::Blinds, $durationSec, axis: $axis);
    }

    public static function box(
        TransitionMotion $motion = TransitionMotion::In,
        float $durationSec = 1.0,
    ): self {
        return new self(TransitionStyle::Box, $durationSec, motion: $motion);
    }

    public static function wipe(
        TransitionDirection $direction = TransitionDirection::Rightward,
        float $durationSec = 1.0,
    ): self {
        return new self(TransitionStyle::Wipe, $durationSec, direction: $direction);
    }

    public static function dissolve(float $durationSec = 1.0): self
    {
        return new self(TransitionStyle::Dissolve, $durationSec);
    }

    public static function glitter(
        TransitionDirection $direction = TransitionDirection::Rightward,
        float $durationSec = 1.0,
    ): self {
        return new self(TransitionStyle::Glitter, $durationSec, direction: $direction);
    }

    public static function fade(float $durationSec = 1.0): self
    {
        return new self(TransitionStyle::Fade, $durationSec);
    }

    public static function push(
        TransitionDirection $direction = TransitionDirection::Rightward,
        float $durationSec = 1.0,
    ): self {
        return new self(TransitionStyle::Push, $durationSec, direction: $direction);
    }

    public static function cover(
        TransitionDirection $direction = TransitionDirection::Rightward,
        float $durationSec = 1.0,
    ): self {
        return new self(TransitionStyle::Cover, $durationSec, direction: $direction);
    }

    public static function uncover(
        TransitionDirection $direction = TransitionDirection::Rightward,
        float $durationSec = 1.0,
    ): self {
        return new self(TransitionStyle::Uncover, $durationSec, direction: $direction);
    }

    public static function fly(
        TransitionDirection $direction = TransitionDirection::Rightward,
        TransitionMotion $motion = TransitionMotion::In,
        float $durationSec = 1.0,
    ): self {
        return new self(TransitionStyle::Fly, $durationSec, direction: $direction, motion: $motion);
    }

    /**
     * The `/Trans << … >>` dictionary body, keys in PDF-spec order (S, D, Dm,
     * Di, M). Deterministic: the duration is formatted with a trimmed
     * fixed-point representation so byte output never depends on locale or
     * float noise.
     */
    public function dictionary(): string
    {
        $parts = ['/Type /Trans', '/S /' . $this->style->pdfName(), '/D ' . self::number($this->durationSec)];

        if ($this->emitsAxis() && $this->axis !== null) {
            $parts[] = '/Dm /' . $this->axis->pdfName();
        }
        if ($this->emitsDirection() && $this->direction !== null) {
            $parts[] = '/Di ' . $this->direction->pdfValue();
        }
        if ($this->emitsMotion() && $this->motion !== null) {
            $parts[] = '/M /' . $this->motion->pdfName();
        }

        return '<<' . implode(' ', $parts) . '>>';
    }

    private function emitsAxis(): bool
    {
        return $this->style === TransitionStyle::Split || $this->style === TransitionStyle::Blinds;
    }

    private function emitsDirection(): bool
    {
        return match ($this->style) {
            TransitionStyle::Wipe,
            TransitionStyle::Glitter,
            TransitionStyle::Push,
            TransitionStyle::Cover,
            TransitionStyle::Uncover,
            TransitionStyle::Fly => true,
            default => false,
        };
    }

    private function emitsMotion(): bool
    {
        return match ($this->style) {
            TransitionStyle::Split, TransitionStyle::Box, TransitionStyle::Fly => true,
            default => false,
        };
    }

    private static function number(float $value): string
    {
        $formatted = rtrim(rtrim(sprintf('%.4F', $value), '0'), '.');

        return $formatted === '' || $formatted === '-0' ? '0' : $formatted;
    }
}
