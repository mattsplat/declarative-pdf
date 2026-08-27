<?php

declare(strict_types=1);

namespace Pdf\Tests\Unit;

use Pdf\Text\Html;
use PHPUnit\Framework\TestCase;

final class HtmlTest extends TestCase
{
    public function test_plain_text_becomes_one_run(): void
    {
        $seq = Html::toInline('just text');

        self::assertSame('just text', $seq->plainText());
    }

    public function test_bold_and_italic_tags_set_run_patches(): void
    {
        $seq = Html::toInline('a <b>bold</b> and <em>italic</em> c');

        self::assertSame('a bold and italic c', $seq->plainText());
        $patches = array_map(static fn ($r) => $r->patch, $seq->runs);
        self::assertTrue($patches[1]->bold);
        self::assertTrue($patches[3]->italic);
        self::assertNull($patches[0]->bold);
    }

    public function test_nested_tags_combine(): void
    {
        $seq = Html::toInline('<b><i>both</i></b>');
        $run = $seq->runs[0];

        self::assertTrue($run->patch->bold);
        self::assertTrue($run->patch->italic);
    }

    public function test_br_inserts_a_hard_break(): void
    {
        $seq = Html::toInline('one<br>two');

        self::assertSame("one\ntwo", $seq->plainText());
    }

    public function test_anchor_becomes_a_link_run(): void
    {
        $seq = Html::toInline('see <a href="https://example.com">the site</a> now');

        $link = null;
        foreach ($seq->runs as $run) {
            if ($run->link !== null) {
                $link = $run;
            }
        }
        self::assertNotNull($link);
        self::assertSame('https://example.com', $link->link);
        self::assertSame('the site', $link->text);
        self::assertTrue($link->patch->underline);
    }

    public function test_sup_and_sub_scale_and_shift(): void
    {
        $seq = Html::toInline('x<sup>2</sup> and H<sub>2</sub>O');

        $sup = $seq->runs[1]->patch;
        $sub = $seq->runs[3]->patch;
        self::assertSame(0.7, $sup->fontSizeScale);
        self::assertGreaterThan(0.0, $sup->baselineShift);
        self::assertLessThan(0.0, $sub->baselineShift);
    }

    public function test_entities_are_decoded_and_whitespace_collapsed(): void
    {
        $seq = Html::toInline("a &amp; b   &mdash;\n  c");

        self::assertSame('a & b — c', $seq->plainText());
    }
}
