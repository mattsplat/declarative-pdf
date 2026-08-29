<?php

declare(strict_types=1);

namespace Pdf\Geometry;

/**
 * The four path-construction operators of the PDF imaging model
 * (PDF 32000-1 §8.5.2). `Close` takes no operands.
 */
enum PathOp: string
{
    case MoveTo = 'm';
    case LineTo = 'l';
    case CurveTo = 'c';
    case Close = 'h';

    /** The content-stream operator this op emits. */
    public function operator(): string
    {
        return $this->value;
    }
}
