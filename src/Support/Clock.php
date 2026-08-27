<?php

declare(strict_types=1);

namespace Pdf\Support;

/**
 * Supplies the current time to the renderer.
 *
 * Injected so golden-file tests can pin `/CreationDate` to a fixed instant.
 * FPDF read `time()` directly in `_enddoc()` (fpdf.php:1950).
 */
interface Clock
{
    public function now(): \DateTimeImmutable;
}
