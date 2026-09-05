<?php

declare(strict_types=1);

namespace Pdf\Tests\Unit;

use Pdf\Font\ToUnicodeCMap;
use Pdf\Import\ToUnicodeCmapParser;
use PHPUnit\Framework\TestCase;

final class ToUnicodeCmapParserTest extends TestCase
{
    public function test_round_trips_the_writer_own_cmap_format(): void
    {
        // Mirrors what FontWriter actually embeds: a contiguous run as a bfrange,
        // plus one-off codes as bfchar.
        $cmap = ToUnicodeCMap::build([
            0x41 => 0x0041, // 'A', via bfchar
            0x20 => [0x0020, 95], // space..~ contiguous run, via bfrange
        ]);

        $map = ToUnicodeCmapParser::parse($cmap);

        self::assertSame('A', $map[0x41]);
        self::assertSame(' ', $map[0x20]);
        self::assertSame('~', $map[0x7E]);
        self::assertSame('0', $map[0x30]);
    }

    public function test_bfrange_with_an_explicit_destination_array(): void
    {
        $cmap = "1 beginbfrange\n<0001> <0003> [<0041> <0042> <0043>]\nendbfrange\n";

        $map = ToUnicodeCmapParser::parse($cmap);

        self::assertSame(['A', 'B', 'C'], [$map[1], $map[2], $map[3]]);
    }

    public function test_ligature_destination_decodes_to_multiple_characters(): void
    {
        // "ffi" as a single glyph code, per the PDF spec's own bfchar example.
        $cmap = "1 beginbfchar\n<00> <006600660069>\nendbfchar\n";

        $map = ToUnicodeCmapParser::parse($cmap);

        self::assertSame('ffi', $map[0x00]);
    }

    public function test_ignores_surrounding_cmap_program_boilerplate(): void
    {
        $cmap = "/CIDInit /ProcSet findresource begin\n12 dict begin\nbegincmap\n"
            . "1 begincodespacerange\n<00> <FF>\nendcodespacerange\n"
            . "1 beginbfchar\n<41> <0041>\nendbfchar\n"
            . "endcmap\nCMapName currentdict /CMap defineresource pop\nend\nend";

        $map = ToUnicodeCmapParser::parse($cmap);

        self::assertSame(['A' => true], array_fill_keys(array_values($map), true));
    }

    public function test_no_bfchar_or_bfrange_blocks_yields_an_empty_map(): void
    {
        self::assertSame([], ToUnicodeCmapParser::parse('begincmap endcmap'));
    }
}
