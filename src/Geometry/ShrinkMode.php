<?php

declare(strict_types=1);

namespace Pdf\Geometry;

/**
 * How placed block content that is taller than its area is made to fit.
 */
enum ShrinkMode
{
    /** Scale the rendered content down geometrically (the default; text strokes thin out). */
    case Scale;
    /** Lower the effective font size until the stack re-flows to fit, then draw it at 1:1. */
    case FontSize;
    /** Leave content at its natural size; anything taller than the area spills past it. */
    case None;
}
