<?php

declare(strict_types=1);

namespace Pdf\Node;

/**
 * A page-to-page transition effect — the PDF `/Trans` dictionary a viewer plays
 * when advancing to a page in full-screen (presentation) mode.
 *
 * Build one with a named constructor; each takes only the parameters its style
 * uses, and the direction argument is a style-scoped type
 * ({@see WipeDirection} / {@see GlitterDirection} / {@see PushDirection}), so a
 * style/direction pair the PDF spec disallows cannot be expressed.
 * {@see self::dictionary()} then emits only the corresponding keys (PDF 2.0,
 * Table 168).
 *
 *   $master = $master->withTransition(Transition::wipe(WipeDirection::Leftward, 0.5));
 */
final readonly class Transition
{
    private function __construct(
        public TransitionStyle $style,
        public float $durationSec = 1.0,
        public ?TransitionAxis $axis = null,
        public ?TransitionMotion $motion = null,
        public ?TransitionDi $direction = null,
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
        WipeDirection $direction = WipeDirection::Rightward,
        float $durationSec = 1.0,
    ): self {
        return new self(TransitionStyle::Wipe, $durationSec, direction: $direction);
    }

    public static function dissolve(float $durationSec = 1.0): self
    {
        return new self(TransitionStyle::Dissolve, $durationSec);
    }

    public static function glitter(
        GlitterDirection $direction = GlitterDirection::Rightward,
        float $durationSec = 1.0,
    ): self {
        return new self(TransitionStyle::Glitter, $durationSec, direction: $direction);
    }

    public static function fade(float $durationSec = 1.0): self
    {
        return new self(TransitionStyle::Fade, $durationSec);
    }

    public static function push(
        PushDirection $direction = PushDirection::Rightward,
        float $durationSec = 1.0,
    ): self {
        return new self(TransitionStyle::Push, $durationSec, direction: $direction);
    }

    public static function cover(
        PushDirection $direction = PushDirection::Rightward,
        float $durationSec = 1.0,
    ): self {
        return new self(TransitionStyle::Cover, $durationSec, direction: $direction);
    }

    public static function uncover(
        PushDirection $direction = PushDirection::Rightward,
        float $durationSec = 1.0,
    ): self {
        return new self(TransitionStyle::Uncover, $durationSec, direction: $direction);
    }

    /**
     * Fly always moves the incoming page as an opaque rectangle: without the
     * PDF `/B` ("fly-in from transparent") or `/SS` (scale) entries — which this
     * library does not model — `Fly` behaves as a Push. It is kept for viewers
     * that special-case it.
     */
    public static function fly(
        PushDirection $direction = PushDirection::Rightward,
        TransitionMotion $motion = TransitionMotion::In,
        float $durationSec = 1.0,
    ): self {
        return new self(TransitionStyle::Fly, $durationSec, motion: $motion, direction: $direction);
    }

    /**
     * The `/Trans << … >>` dictionary body, keys in Table 168 order (S, D, Dm,
     * M, Di). Only the keys the style set are present. Deterministic: the
     * duration is a trimmed fixed-point string, locale-independent.
     */
    public function dictionary(): string
    {
        $parts = ['/Type /Trans', '/S /' . $this->style->pdfName(), '/D ' . self::number($this->durationSec)];

        if ($this->axis !== null) {
            $parts[] = '/Dm /' . $this->axis->pdfName();
        }
        if ($this->motion !== null) {
            $parts[] = '/M /' . $this->motion->pdfName();
        }
        if ($this->direction !== null) {
            $parts[] = '/Di ' . $this->direction->pdfValue();
        }

        return '<<' . implode(' ', $parts) . '>>';
    }

    /**
     * Whether this transition uses a construct introduced in PDF 1.5 — the
     * `/Di` and `/M` entries, and the Fade / Push / Cover / Uncover / Fly
     * styles. Blinds and Dissolve without motion are PDF 1.1.
     */
    public function requiresPdf15(): bool
    {
        if ($this->direction !== null || $this->motion !== null) {
            return true;
        }

        return match ($this->style) {
            TransitionStyle::Fade,
            TransitionStyle::Push,
            TransitionStyle::Cover,
            TransitionStyle::Uncover,
            TransitionStyle::Fly => true,
            default => false,
        };
    }

    private static function number(float $value): string
    {
        $formatted = rtrim(rtrim(sprintf('%.4F', $value), '0'), '.');

        return $formatted === '' || $formatted === '-0' ? '0' : $formatted;
    }
}
