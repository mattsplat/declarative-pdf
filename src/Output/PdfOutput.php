<?php

declare(strict_types=1);

namespace Pdf\Output;

use Pdf\Exception\PdfException;

/**
 * Holds a rendered PDF byte string and sends it somewhere.
 *
 * Ports the destinations of `Output()` (fpdf.php:984): inline, download, file
 * and string, plus the `_checkoutput()` guard (fpdf.php:1041) and the
 * `_httpencode()` RFC 5987 filename encoding (fpdf.php:1190).
 */
final readonly class PdfOutput
{
    public function __construct(private string $bytes)
    {
    }

    public function toString(): string
    {
        return $this->bytes;
    }

    public function save(string $path): void
    {
        if (@file_put_contents($path, $this->bytes) === false) {
            throw new PdfException('Unable to create output file: ' . $path);
        }
    }

    public function inline(string $name = 'doc.pdf'): void
    {
        $this->checkOutput();
        if (PHP_SAPI !== 'cli') {
            header('Content-Type: application/pdf');
            header('Content-Disposition: inline; ' . $this->encodeFilename($name));
            header('Cache-Control: private, max-age=0, must-revalidate');
            header('Pragma: public');
        }
        echo $this->bytes;
    }

    public function download(string $name = 'doc.pdf'): void
    {
        $this->checkOutput();
        header('Content-Type: application/pdf');
        header('Content-Disposition: attachment; ' . $this->encodeFilename($name));
        header('Cache-Control: private, max-age=0, must-revalidate');
        header('Pragma: public');
        echo $this->bytes;
    }

    private function checkOutput(): void
    {
        if (PHP_SAPI !== 'cli' && headers_sent($file, $line)) {
            throw new PdfException("Some data has already been output, can't send PDF file (output started at {$file}:{$line})");
        }
        if (ob_get_length()) {
            if (preg_match('/^(\xEF\xBB\xBF)?\s*$/', (string) ob_get_contents())) {
                ob_clean();
            } else {
                throw new PdfException("Some data has already been output, can't send PDF file");
            }
        }
    }

    private function encodeFilename(string $name): string
    {
        if (!preg_match('/[\x80-\xFF]/', $name)) {
            return 'filename="' . $name . '"';
        }

        return "filename*=UTF-8''" . rawurlencode($name);
    }
}
