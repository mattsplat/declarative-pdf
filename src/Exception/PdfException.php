<?php

declare(strict_types=1);

namespace Pdf\Exception;

/**
 * Base class for every error raised by the library.
 *
 * Replaces FPDF's `Error()` helper, which threw a bare \Exception
 * (fpdf.php:264).
 */
class PdfException extends \RuntimeException
{
}
