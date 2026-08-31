<?php

declare(strict_types=1);

namespace Pdf\Node;

/**
 * The `/Di` value for a Push, Cover, Uncover or Fly transition. These styles
 * admit only left-to-right (`0`) and top-to-bottom (`270`) motion (PDF 2.0,
 * Table 168).
 */
enum PushDirection: int implements TransitionDi
{
    case Rightward = 0;
    case Downward = 270;

    public function pdfValue(): string
    {
        return (string) $this->value;
    }
}
