<?php

declare(strict_types=1);

namespace Pdf\Tests\Unit;

use Pdf\Color\Color;
use Pdf\Document;
use Pdf\Font\FontRepository;
use Pdf\Font\FontStyle;
use Pdf\Layout\LineBreaker;
use Pdf\Layout\ResolvedRun;
use Pdf\Layout\TableLayout;
use Pdf\Node\Container;
use Pdf\Node\Document as DocumentTree;
use Pdf\Node\Page;
use Pdf\Node\PageBreak;
use Pdf\Node\Paragraph;
use Pdf\Style\ColumnWidth;
use Pdf\Style\StylePatch;
use Pdf\Tests\Support\FakeBox;
use Pdf\Tests\Support\Fonts;
use Pdf\Tests\Support\Pdf;
use Pdf\Text\Encoding;
use Pdf\Text\Html;
use PHPUnit\Framework\TestCase;

final class ReviewRegressionsTest extends TestCase
{
    public function test_page_break_nested_in_a_container_is_honoured(): void
    {
        $tree = new DocumentTree([
            new Page(children: [
                new Container([
                    new Paragraph('before the break'),
                    new PageBreak(),
                    new Paragraph('after the break'),
                ]),
            ]),
        ]);

        $pdf = Pdf::deterministicRenderer()->render($tree);
        $pageCount = substr_count($pdf, '/Type /Page') - substr_count($pdf, '/Type /Pages');

        self::assertSame(2, $pageCount);
    }

    public function test_stack_split_drags_an_anchor_forward_with_its_block(): void
    {
        $anchor = new \Pdf\Layout\Box\AnchorBox('methods');
        $heading = new FakeBox('heading', 20.0, keepWithNext: true);
        $body = new FakeBox('body', 400.0);

        $stack = new \Pdf\Layout\Box\StackBox([
            new FakeBox('filler', 50.0),
            $anchor,
            $heading,
            $body,
        ]);

        [$head, $tail] = $stack->split(80.0);

        $headLabels = array_map(
            static fn ($b) => $b instanceof FakeBox ? $b->label : $b::class,
            $head?->children() ?? [],
        );
        $tailLabels = array_map(
            static fn ($b) => $b instanceof FakeBox ? $b->label : $b::class,
            $tail?->children() ?? [],
        );

        self::assertSame(['filler'], $headLabels, 'the anchor did not stay behind');
        self::assertSame([\Pdf\Layout\Box\AnchorBox::class, 'heading', 'body'], $tailLabels);
    }

    public function test_anchor_dest_resolves_and_is_not_stranded(): void
    {
        $children = [
            new Paragraph(\Pdf\Text\InlineSequence::of('Jump to ')->withLink('methods', '#methods')),
        ];
        for ($i = 0; $i < 14; $i++) {
            $children[] = new Paragraph(str_repeat('filler that consumes vertical space ', 12));
        }
        $children[] = new \Pdf\Node\Anchor('methods');
        $children[] = new \Pdf\Node\Heading(2, 'Methods Section');
        $children[] = new Paragraph(str_repeat('body of the methods section ', 30));

        $pdf = Pdf::deterministicRenderer()->render(new DocumentTree([new Page(children: $children)]));

        self::assertSame(1, preg_match('/\/Dest \[(\d+) 0 R \/XYZ 0 ([\d.]+) null\]/', $pdf, $m));
        self::assertGreaterThan(100.0, (float) $m[2], 'anchor is not stranded at a page bottom');
        self::assertStringContainsString('(Methods Section) Tj', Pdf::contentText($pdf));
    }

    public function test_html_keeps_unknown_angle_bracket_spans_as_text(): void
    {
        self::assertSame('use Map<String,Int> for this', Html::toInline('use Map<String,Int> for this')->plainText());
        self::assertSame('a <= b', Html::toInline('a &lt;= b')->plainText());
        // still recognises real tags with attributes
        $seq = Html::toInline('a <b class="x">bold</b> c');
        self::assertTrue($seq->runs[1]->patch->bold);
    }

    public function test_font_repository_registration_wins_over_the_arial_alias(): void
    {
        $repo = FontRepository::withBundledFonts();
        $repo->register('Arial', FontStyle::Regular, dirname(__DIR__) . '/fixtures/CevicheOne-Regular.json');

        self::assertSame('CevicheOne-Regular', $repo->resolve('Arial', FontStyle::Regular)->name);
    }

    public function test_font_repository_registration_after_first_resolve_takes_effect(): void
    {
        $repo = FontRepository::withBundledFonts();
        self::assertSame('Helvetica', $repo->resolve('Helvetica', FontStyle::Regular)->name);

        $repo->register('Helvetica', FontStyle::Regular, dirname(__DIR__) . '/fixtures/CevicheOne-Regular.json');
        self::assertSame('CevicheOne-Regular', $repo->resolve('Helvetica', FontStyle::Regular)->name);
    }

    public function test_table_layout_never_pushes_a_column_past_its_max_clamp(): void
    {
        $widths = TableLayout::resolve(300.0, [
            ColumnWidth::auto(maxPt: 80.0),
            ColumnWidth::auto(),
        ], [
            ['min' => 10.0, 'max' => 500.0],
            ['min' => 10.0, 'max' => 500.0],
        ]);

        self::assertLessThanOrEqual(80.0 + 1e-6, $widths[0]);
        self::assertEqualsWithDelta(300.0, array_sum($widths), 1e-6);
    }

    public function test_all_fixed_columns_grow_to_fill_the_available_width(): void
    {
        $widths = TableLayout::resolve(500.0, [
            ColumnWidth::fixed(100.0),
            ColumnWidth::fixed(100.0),
        ], [
            ['min' => 0.0, 'max' => 0.0],
            ['min' => 0.0, 'max' => 0.0],
        ]);

        self::assertEqualsWithDelta(500.0, array_sum($widths), 1e-6);
        self::assertEqualsWithDelta($widths[0], $widths[1], 1e-6);
    }

    public function test_all_fixed_columns_wider_than_the_page_are_left_alone(): void
    {
        $widths = TableLayout::resolve(200.0, [
            ColumnWidth::fixed(150.0),
            ColumnWidth::fixed(150.0),
        ], [
            ['min' => 0.0, 'max' => 0.0],
            ['min' => 0.0, 'max' => 0.0],
        ]);

        self::assertSame([150.0, 150.0], $widths);
    }

    public function test_encoding_uses_iconv_for_code_pages_mbstring_lacks(): void
    {
        // U+010D (č) is 0xE8 in Windows-1250.
        $out = Encoding::forFont("\u{010D}", 'cp1250');

        self::assertSame("\xE8", $out);
    }

    public function test_line_breaker_hard_splits_a_word_that_straddles_a_style_change(): void
    {
        $lines = (new LineBreaker())->break([
            new ResolvedRun('supercalifragilistic', Fonts::helvetica(), 12.0, Color::black()),
            new ResolvedRun('expialidocious', Fonts::helvetica(FontStyle::Bold), 12.0, Color::black()),
        ], 90.0, 1.2);

        // The joined word gets split at a character, not shown as two words on
        // two lines.
        self::assertGreaterThan(1, count($lines));
        $joined = '';
        foreach ($lines as $line) {
            foreach ($line->fragments as $fragment) {
                $joined .= $fragment->text;
            }
        }
        self::assertSame('supercalifragilisticexpialidocious', $joined);
        // At least one line ends mid-"supercalifragilistic" (before the run boundary).
        self::assertStringStartsWith('supercalifragilistic', $joined);
        self::assertLessThan(strlen('supercalifragilistic'), strlen($lines[0]->fragments[0]->text));
    }
}
