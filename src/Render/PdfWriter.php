<?php

declare(strict_types=1);

namespace Pdf\Render;

use Pdf\Exception\PdfException;

/**
 * Append-only byte buffer for the PDF file, plus object/stream helpers.
 *
 * Ports `_put()` (fpdf.php:1520), `_newobj()` (fpdf.php:1531),
 * `_putstream()` (fpdf.php:1540), `_putstreamobject()` (fpdf.php:1547) and the
 * xref/trailer assembly of `_enddoc()` (fpdf.php:1966-1982).
 */
final class PdfWriter
{
    private string $buffer = '';

    public function __construct(
        private readonly ObjectRegistry $registry,
        private readonly bool $compress = true,
    ) {
    }

    public function registry(): ObjectRegistry
    {
        return $this->registry;
    }

    /** Append one line (FPDF's `_put`). */
    public function line(string $s): void
    {
        $this->buffer .= $s . "\n";
    }

    public function length(): int
    {
        return strlen($this->buffer);
    }

    /** Write `%PDF-x.y` (ports `_putheader()`, fpdf.php:1936). */
    public function header(string $version): void
    {
        $this->line('%PDF-' . $version);
    }

    /**
     * Begin an object. With no number, allocates the next one.
     * Returns the object number.
     */
    public function beginObject(?int $object = null): int
    {
        $object ??= $this->registry->allocate();
        $this->registry->recordOffset($object, $this->length());
        $this->line($object . ' 0 obj');

        return $object;
    }

    public function endObject(): void
    {
        $this->line('endobj');
    }

    public function stream(string $data): void
    {
        $this->line('stream');
        $this->line($data);
        $this->line('endstream');
    }

    /**
     * Write a complete stream object, compressing when enabled.
     * `$extraDict` holds dictionary entries other than `/Filter` and `/Length`.
     * Returns the object number.
     */
    public function streamObject(string $data, string $extraDict = ''): int
    {
        return $this->streamObjectAt(null, $data, $extraDict);
    }

    /**
     * Write a stream object, optionally at a pre-allocated object number.
     * Returns the object number.
     */
    public function streamObjectAt(?int $object, string $data, string $extraDict = ''): int
    {
        $entries = $extraDict;
        if ($this->compress) {
            $compressed = gzcompress($data);
            if ($compressed === false) {
                throw new PdfException('Stream compression failed.');
            }
            $data = $compressed;
            $entries .= '/Filter /FlateDecode ';
        }
        $entries .= '/Length ' . strlen($data);

        $object = $this->beginObject($object);
        $this->line('<<' . $entries . '>>');
        $this->stream($data);
        $this->endObject();

        return $object;
    }

    /**
     * Finish the file: xref table, trailer, startxref, %%EOF.
     * `$root` and `$info` are the catalog and info object numbers.
     */
    public function finish(int $root, int $info): void
    {
        $count = $this->registry->current();
        $offsets = $this->registry->offsets();
        $xrefOffset = $this->length();

        $this->line('xref');
        $this->line('0 ' . ($count + 1));
        $this->line('0000000000 65535 f ');
        for ($i = 1; $i <= $count; $i++) {
            $this->line(sprintf('%010d 00000 n ', $offsets[$i] ?? 0));
        }

        $this->line('trailer');
        $this->line('<<');
        $this->line('/Size ' . ($count + 1));
        $this->line('/Root ' . $root . ' 0 R');
        $this->line('/Info ' . $info . ' 0 R');
        $this->line('>>');
        $this->line('startxref');
        $this->line((string) $xrefOffset);
        $this->line('%%EOF');
    }

    public function toBytes(): string
    {
        return $this->buffer;
    }
}
