<?php

declare(strict_types=1);

namespace Pdf\Node;

/**
 * The `/Di` value for a Glitter transition. Glitter admits only left-to-right
 * (`0`), top-to-bottom (`270`) and top-left-to-bottom-right (`315`); the `315`
 * diagonal is unique to Glitter (PDF 2.0, Table 168).
 */
enum GlitterDirection: int implements TransitionDi
{
    case Rightward = 0;
    case Downward = 270;
    case Diagonal = 315;

    public function pdfValue(): string
    {
        return (string) $this->value;
    }
}
