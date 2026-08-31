<?php

declare(strict_types=1);

namespace Pdf\Node;

/**
 * A `/Di` (direction of motion) value for a `/Trans` dictionary.
 *
 * Each transition style admits only a subset of the angles, so the concrete
 * implementations are style-scoped ({@see WipeDirection}, {@see GlitterDirection},
 * {@see PushDirection}) and the {@see Transition} factories accept the matching
 * type — an out-of-spec style/direction pair cannot be constructed.
 */
interface TransitionDi
{
    /** The `/Di` value: an angle in degrees, e.g. `0` or `270`. */
    public function pdfValue(): string;
}
