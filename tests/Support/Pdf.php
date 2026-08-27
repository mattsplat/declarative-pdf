<?php

declare(strict_types=1);

namespace Pdf\Tests\Support;

use Pdf\Font\FontRepository;
use Pdf\Render\DocumentRenderer;
use Pdf\Support\FixedClock;

final class Pdf
{
    /** A renderer with a pinned clock and no compression, for readable output. */
    public static function deterministicRenderer(): DocumentRenderer
    {
        return new DocumentRenderer(
            fontRepository: FontRepository::withBundledFonts(),
            clock: FixedClock::at('2026-08-26T12:00:00+00:00'),
            compress: false,
            producer: 'fpdf/pdf-test',
        );
    }

    /** Concatenate every content stream in a (non-compressed) PDF. */
    public static function contentText(string $pdf): string
    {
        $out = '';
        if (preg_match_all('/stream\r?\n(.*?)\r?\nendstream/s', $pdf, $matches)) {
            foreach ($matches[1] as $chunk) {
                $out .= $chunk . "\n";
            }
        }

        return $out;
    }

    /** @return list<int> object numbers that have an xref entry */
    public static function objectNumbers(string $pdf): array
    {
        preg_match_all('/(\d+) 0 obj/', $pdf, $m);

        return array_map('intval', $m[1]);
    }
}
