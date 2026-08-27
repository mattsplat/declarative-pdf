<?php

declare(strict_types=1);

namespace Pdf\Font;

/**
 * Builds a ToUnicode CMap stream from a font's `uv` map.
 *
 * Ported verbatim from `_tounicodecmap()` (fpdf.php:1771).
 */
final class ToUnicodeCMap
{
    /** @param array<int|string, int|array{0:int,1:int}> $uv */
    public static function build(array $uv): string
    {
        $ranges = '';
        $numRanges = 0;
        $chars = '';
        $numChars = 0;

        foreach ($uv as $code => $value) {
            $code = (int) $code;
            if (is_array($value)) {
                $ranges .= sprintf("<%02X> <%02X> <%04X>\n", $code, $code + $value[1] - 1, $value[0]);
                $numRanges++;
            } else {
                $chars .= sprintf("<%02X> <%04X>\n", $code, $value);
                $numChars++;
            }
        }

        $s = "/CIDInit /ProcSet findresource begin\n";
        $s .= "12 dict begin\n";
        $s .= "begincmap\n";
        $s .= "/CIDSystemInfo\n";
        $s .= "<</Registry (Adobe)\n";
        $s .= "/Ordering (UCS)\n";
        $s .= "/Supplement 0\n";
        $s .= ">> def\n";
        $s .= "/CMapName /Adobe-Identity-UCS def\n";
        $s .= "/CMapType 2 def\n";
        $s .= "1 begincodespacerange\n";
        $s .= "<00> <FF>\n";
        $s .= "endcodespacerange\n";
        if ($numRanges > 0) {
            $s .= "{$numRanges} beginbfrange\n";
            $s .= $ranges;
            $s .= "endbfrange\n";
        }
        if ($numChars > 0) {
            $s .= "{$numChars} beginbfchar\n";
            $s .= $chars;
            $s .= "endbfchar\n";
        }
        $s .= "endcmap\n";
        $s .= "CMapName currentdict /CMap defineresource pop\n";
        $s .= "end\n";
        $s .= 'end';

        return $s;
    }
}
