<?php

declare(strict_types=1);

namespace Pdf\Node;

/**
 * The `/Di` entry of a `/Trans` dictionary — the direction the transition
 * travels, given as an angle in degrees (or `/None` for a purely axial Fly).
 *
 * `0` is left-to-right, then counter-clockwise: `90` bottom-to-top, `180`
 * right-to-left, `270` top-to-bottom, `315` top-left-to-bottom-right (Glitter,
 * Cover, Uncover only).
 */
enum TransitionDirection
{
    case Rightward;
    case Upward;
    case Leftward;
    case Downward;
    case Diagonal;
    case None;

    /** The `/Di` value, including the leading slash for the `/None` name. */
    public function pdfValue(): string
    {
        return match ($this) {
            self::Rightward => '0',
            self::Upward => '90',
            self::Leftward => '180',
            self::Downward => '270',
            self::Diagonal => '315',
            self::None => '/None',
        };
    }
}
