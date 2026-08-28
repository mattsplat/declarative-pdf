<?php

declare(strict_types=1);

namespace Pdf\Tests\Unit;

use Pdf\Color\Color;
use Pdf\Font\FontFace;
use Pdf\Layout\LineBreaker;
use Pdf\Layout\ResolvedRun;
use Pdf\Layout\TextLine;
use Pdf\Tests\Support\Fonts;
use PHPUnit\Framework\TestCase;

final class LineBreakerTest extends TestCase
{
    private LineBreaker $breaker;

    protected function setUp(): void
    {
        $this->breaker = new LineBreaker();
    }

    /** @param list<TextLine> $lines */
    private static function text(array $lines): string
    {
        return implode("\n", array_map(
            static fn (TextLine $l) => implode('', array_map(
                static fn ($f) => $f->text,
                $l->fragments,
            )),
            $lines,
        ));
    }

    private function mkRun(string $text, float $size = 12.0): ResolvedRun
    {
        return new ResolvedRun($text, Fonts::helvetica(), $size, Color::black());
    }

    public function test_short_text_is_a_single_line(): void
    {
        $lines = $this->breaker->break([$this->mkRun('Hello world')], 500.0, 1.0);

        self::assertCount(1, $lines);
        self::assertSame('Hello world', self::text($lines));
        self::assertTrue($lines[0]->isBreakLine);
    }

    public function test_wraps_at_spaces_when_the_line_overflows(): void
    {
        $lines = $this->breaker->break(
            [$this->mkRun('one two three four five six seven eight nine ten')],
            60.0,
            1.0,
        );

        self::assertGreaterThan(1, count($lines));
        foreach ($lines as $line) {
            self::assertStringNotContainsString('  ', self::text([$line]));
        }
        // No word is lost or duplicated.
        self::assertSame(
            'one two three four five six seven eight nine ten',
            str_replace("\n", ' ', self::text($lines)),
        );
    }

    public function test_explicit_newline_forces_a_break(): void
    {
        $lines = $this->breaker->break([$this->mkRun("a\nb\nc")], 500.0, 1.0);

        self::assertSame("a\nb\nc", self::text($lines));
        self::assertCount(3, $lines);
    }

    public function test_long_unbreakable_word_is_hard_split(): void
    {
        $lines = $this->breaker->break([$this->mkRun('supercalifragilisticexpialidocious')], 40.0, 1.0);

        self::assertGreaterThan(1, count($lines));
        self::assertSame(
            'supercalifragilisticexpialidocious',
            str_replace("\n", '', self::text($lines)),
        );
    }

    public function test_only_non_final_lines_with_gaps_are_justifiable(): void
    {
        $lines = $this->breaker->break(
            [$this->mkRun('alpha beta gamma delta epsilon zeta eta theta iota kappa')],
            80.0,
            1.0,
        );

        $last = $lines[array_key_last($lines)];
        self::assertTrue($last->isBreakLine);
        self::assertSame(0, $last->justifiableGaps);
        self::assertGreaterThan(0, $lines[0]->justifiableGaps);
    }

    public function test_breaks_across_runs_of_different_styles(): void
    {
        $lines = $this->breaker->break([
            new ResolvedRun('bold words here ', Fonts::helvetica(FontFace::bold()), 12.0, Color::black()),
            new ResolvedRun('and regular words following', Fonts::helvetica(), 12.0, Color::black()),
        ], 90.0, 1.0);

        self::assertSame(
            'bold words here and regular words following',
            str_replace("\n", ' ', self::text($lines)),
        );
        // The first line should carry fragments from at least one run.
        self::assertNotEmpty($lines[0]->fragments);
    }
}
