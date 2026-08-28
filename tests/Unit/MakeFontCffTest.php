<?php

declare(strict_types=1);

namespace Pdf\Tests\Unit;

use Pdf\Font\FontLoader;
use PHPUnit\Framework\TestCase;

/**
 * `tools/makefont/` on an OpenType font with PostScript (CFF) outlines.
 *
 * The tool is FPDF-era procedural code with a CLI entry point, so it is driven
 * as a subprocess in a scratch directory — it writes its output next to the cwd.
 */
final class MakeFontCffTest extends TestCase
{
    /**
     * Advances read straight out of the fixture's `hmtx`/`cmap` (units/1000em,
     * unitsPerEm = 1000), the ground truth `cw` has to reproduce.
     */
    private const ADVANCES = [
        'H' => 707, 'a' => 534, 'm' => 873, 'b' => 580, 'u' => 568,
        'r' => 367, 'g' => 528, 'e' => 549, 'f' => 324, 'o' => 560,
        'n' => 568, 's' => 487, 't' => 351, 'i' => 250, 'v' => 492,
        ' ' => 236,
    ];

    private string $directory;

    /** @var array<string, mixed>|null */
    private ?array $definition = null;

    protected function setUp(): void
    {
        $this->directory = sys_get_temp_dir() . '/makefont-cff-' . bin2hex(random_bytes(6));
        if (!mkdir($this->directory) || !copy(self::fixture('IBMPlexSans-Regular.otf'), $this->directory . '/IBMPlexSans-Regular.otf')) {
            self::fail('Could not stage the font fixture in ' . $this->directory);
        }
    }

    protected function tearDown(): void
    {
        foreach ((array) glob($this->directory . '/*') as $file) {
            unlink((string) $file);
        }
        rmdir($this->directory);
    }

    public function test_generates_a_type1_cff_definition_from_an_otf(): void
    {
        $data = $this->makeFont();

        self::assertSame('Type1', $data['type'], 'CFF outlines are embedded as a Type1 font');
        self::assertTrue($data['cff']);
        self::assertSame('IBMPlexSans', $data['name']);
        self::assertSame('IBMPlexSans-Regular.cff.z', $data['file']);
        self::assertNotNull($data['size1'] ?? null);
        self::assertSame(strlen(self::sfntTable(self::fixture('IBMPlexSans-Regular.otf'), 'CFF ')), $data['size1']);
        self::assertArrayNotHasKey('size2', $data, 'a bare CFF has no encrypted segment');
        self::assertArrayNotHasKey('subsetted', $data, 'v1 embeds the whole font program');
        self::assertSame($data['size1'], $data['originalsize']);
        self::assertCount(256, $data['cw']);
    }

    public function test_widths_come_from_the_hmtx_advances(): void
    {
        $widths = $this->makeFont()['cw'];

        foreach (self::ADVANCES as $character => $advance) {
            self::assertSame($advance, $widths[ord($character)], "advance of '{$character}'");
        }

        // Descriptor metrics from head/OS/2/post, likewise unscaled (unitsPerEm = 1000).
        $descriptor = $this->makeFont()['desc'];
        self::assertSame(780, $descriptor['Ascent']);
        self::assertSame(-220, $descriptor['Descent']);
        self::assertSame(698, $descriptor['CapHeight']);
        self::assertSame('[-260 -245 1241 1119]', $descriptor['FontBBox']);
        self::assertSame(472, $descriptor['MissingWidth'], 'the .notdef advance');
    }

    public function test_the_embedded_program_is_the_cff_table_verbatim(): void
    {
        $data = $this->makeFont();
        $program = (string) gzuncompress((string) file_get_contents($this->directory . '/' . $data['file']));

        self::assertSame($data['size1'], strlen($program));
        self::assertSame("\x01\x00", substr($program, 0, 2), 'CFF header major/minor version');

        $table = self::sfntTable(self::fixture('IBMPlexSans-Regular.otf'), 'CFF ');
        self::assertSame($table, $program);
    }

    public function test_rejects_cff2_variable_fonts(): void
    {
        $font = (string) file_get_contents(self::fixture('IBMPlexSans-Regular.otf'));
        $tampered = str_replace('CFF ', 'CFF2', $font);
        file_put_contents($this->directory . '/Tampered.otf', $tampered);

        $output = $this->runMakeFont('Tampered.otf');

        self::assertStringContainsString('CFF2 (OpenType variable) fonts are not supported', $output);
        self::assertFileDoesNotExist($this->directory . '/Tampered.json');
    }

    public function test_detects_the_cid_keyed_top_dict_operator(): void
    {
        require_once dirname(__DIR__, 2) . '/tools/makefont/ttfparser.php';
        $parser = new \TTFParser(self::fixture('IBMPlexSans-Regular.otf'));

        // ROS is operator 12 30; two operands precede it in a real CID Top DICT.
        self::assertTrue($parser->IsCIDKeyed("\x8b\x8c\x0c\x1e"));
        self::assertTrue($parser->IsCIDKeyed("\x1e\xa1\x2f\x0c\x1e"), 'after a real-number operand');

        self::assertFalse($parser->IsCIDKeyed("\x8b\x0c\x00\xf8\x1b\x11"), 'a plain Top DICT');
        self::assertFalse($parser->IsCIDKeyed("\x1c\x0c\x1e\x00\x11"), '12 30 inside a 3-byte operand');
    }

    public function test_the_definition_loads_as_a_cff_font(): void
    {
        $definition = (new FontLoader())->load(self::fixture('IBMPlexSans-Regular.json'));

        self::assertTrue($definition->isCff);
        self::assertFalse($definition->subsetted);
        self::assertSame('Type1', $definition->type);
        self::assertSame(60818, $definition->size1);
        self::assertNull($definition->size2);
    }

    /** @return array<string, mixed> */
    private function makeFont(): array
    {
        if ($this->definition !== null) {
            return $this->definition;
        }

        $output = $this->runMakeFont('IBMPlexSans-Regular.otf');
        $json = @file_get_contents($this->directory . '/IBMPlexSans-Regular.json');
        if ($json === false) {
            self::fail('makefont.php produced no definition file: ' . $output);
        }

        self::assertStringContainsString('Subsetting is not supported', $output, 'the subset request is declined, not silently honoured');

        /** @var array<string, mixed> $data */
        $data = json_decode($json, true, flags: JSON_THROW_ON_ERROR);

        return $this->definition = $data;
    }

    private function runMakeFont(string $fontFile): string
    {
        return (string) shell_exec(sprintf(
            'cd %s && %s %s %s cp1252 2>&1',
            escapeshellarg($this->directory),
            escapeshellarg(PHP_BINARY),
            escapeshellarg(dirname(__DIR__, 2) . '/tools/makefont/makefont.php'),
            escapeshellarg($fontFile),
        ));
    }

    private static function fixture(string $name): string
    {
        return dirname(__DIR__) . '/fixtures/' . $name;
    }

    /** Read one sfnt table out of a font file, independently of the parser under test. */
    private static function sfntTable(string $path, string $tag): string
    {
        $font = (string) file_get_contents($path);
        $tableCount = (int) unpack('n', substr($font, 4, 2))[1];

        for ($i = 0; $i < $tableCount; $i++) {
            $entry = substr($font, 12 + 16 * $i, 16);
            if (substr($entry, 0, 4) === $tag) {
                /** @var array{offset:int, length:int} $header */
                $header = unpack('Noffset/Nlength', substr($entry, 8, 8));

                return substr($font, $header['offset'], $header['length']);
            }
        }

        self::fail("Table {$tag} not found in {$path}");
    }
}
