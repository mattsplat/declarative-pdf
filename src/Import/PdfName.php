<?php

declare(strict_types=1);

namespace Pdf\Import;

/** A PDF name object (`/Foo`). Distinct from a string so serialisation is faithful. */
final readonly class PdfName
{
    public function __construct(public string $value)
    {
    }
}
