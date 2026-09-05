<?php

declare(strict_types=1);

/*
 * Pull plain text back out of a PDF.
 *
 * TextExtractor is best-effort: it decodes text through each font's
 * /ToUnicode CMap (or WinAnsi as a fallback), and uses glyph positions to
 * guess where a space or a line break belongs when the content stream
 * doesn't say so explicitly (e.g. table cells drawn as separate runs). It
 * does not attempt column-aware reading order, and it does not descend into
 * Form XObjects.
 */

require __DIR__ . '/../vendor/autoload.php';

use Pdf\Document;
use Pdf\Import\TextExtractor;
use Pdf\Node\Table;
use Pdf\Node\TableCell;
use Pdf\Node\TableRow;
use Pdf\Style\ColumnWidth;

Document::create()
    ->page(fn ($p) => $p
        ->heading(1, 'Quarterly Review')
        ->paragraph('Revenue grew across every region this quarter, led by the launch of the new pricing tiers.')
        ->add(new Table(
            [
                new TableRow([new TableCell('Line'), new TableCell('Q2'), new TableCell('Q3')]),
                new TableRow([new TableCell('Revenue'), new TableCell('12.4'), new TableCell('13.9')]),
            ],
            [ColumnWidth::fraction(1.0), ColumnWidth::fixed(60.0), ColumnWidth::fixed(60.0)],
            headerRows: 1,
        )))
    ->save(__DIR__ . '/extract-text.pdf');

foreach (TextExtractor::fromFile(__DIR__ . '/extract-text.pdf')->pages() as $n => $text) {
    echo "--- page " . ($n + 1) . " ---\n{$text}\n";
}
