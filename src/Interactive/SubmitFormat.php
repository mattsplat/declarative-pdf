<?php

declare(strict_types=1);

namespace Pdf\Interactive;

/**
 * Wire format a submit button asks the viewer to POST the field values in.
 *
 * The bit is the `/Flags` value of the `/SubmitForm` action: `ExportFormat`
 * (bit 3) selects HTML/FDF, `XFDF` (bit 6) selects XFDF, `SubmitPDF` (bit 9)
 * sends the whole file. FDF is the default and the most widely understood.
 */
enum SubmitFormat: int
{
    case Fdf = 0;
    case Html = 0b100;          // ExportFormat
    case Xfdf = 0b100000;       // XFDF
    case Pdf = 0b100000000;     // SubmitPDF

    public function actionFlags(): int
    {
        return $this->value;
    }
}
