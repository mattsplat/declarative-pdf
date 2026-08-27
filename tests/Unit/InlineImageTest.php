<?php

declare(strict_types=1);

namespace Pdf\Tests\Unit;

use Pdf\Color\Color;
use Pdf\Layout\Inline\BoxItem;
use Pdf\Layout\Inline\WordItem;
use Pdf\Layout\LineBreaker;
use Pdf\Layout\ResolvedRun;
use Pdf\Layout\RunStyle;
use Pdf\Tests\Support\Fonts;
use PHPUnit\Framework\TestCase;

final class InlineImageTest extends TestCase
{
    private function textRun(string $text): ResolvedRun
    {
        return new ResolvedRun($text, Fonts::helvetica(), 12.0, Color::black());
    }

    private function imageRun(float $w, float $h): ResolvedRun
    {
        return new ResolvedRun(
            text: '',
            font: Fonts::helvetica(),
            fontSizePt: 12.0,
            color: Color::black(),
            imageIndex: 1,
            imageWidthPt: $w,
            imageHeightPt: $h,
        );
    }

    public function test_word_split_at_produces_a_prefix_that_fits(): void
    {
        $word = WordItem::of(
            'supercalifragilistic',
            new RunStyle(Fonts::helvetica(), 12.0, Color::black()),
        );
        [$head, $tail] = $word->splitAt(30.0);

        self::assertNotNull($tail);
        self::assertLessThanOrEqual(30.0 + 1e-6, $head->widthPt);
        self::assertSame('supercalifragilistic', $head->text . $tail->text);
        self::assertNotSame('', $head->text);
    }

    public function test_inline_image_flows_with_text_and_wraps(): void
    {
        $lines = (new LineBreaker())->break([
            $this->textRun('before '),
            $this->imageRun(40.0, 20.0),
            $this->textRun(' after the image continues with several more words to force a wrap here'),
        ], 120.0, 1.2);

        self::assertGreaterThan(1, count($lines));

        // Exactly one image fragment across all lines, on the first line.
        $imageFragments = 0;
        foreach ($lines as $li => $line) {
            foreach ($line->fragments as $fragment) {
                if ($fragment->isImage()) {
                    $imageFragments++;
                    self::assertSame(0, $li, 'image stays on the first line');
                    self::assertSame(40.0, $fragment->widthPt);
                }
            }
        }
        self::assertSame(1, $imageFragments);
    }

    public function test_line_height_grows_to_fit_a_tall_inline_image(): void
    {
        $short = (new LineBreaker())->break([$this->textRun('just text')], 500.0, 1.2);
        $withImage = (new LineBreaker())->break([
            $this->textRun('x '),
            $this->imageRun(30.0, 60.0),
        ], 500.0, 1.2);

        self::assertGreaterThan($short[0]->heightPt, $withImage[0]->heightPt);
        self::assertGreaterThanOrEqual(60.0, $withImage[0]->ascentPt);
    }

    public function test_tokenizer_emits_a_box_item_for_an_image_run(): void
    {
        $method = new \ReflectionMethod(LineBreaker::class, 'tokenize');
        $items = $method->invoke(new LineBreaker(), [$this->imageRun(10.0, 10.0)]);

        self::assertCount(1, $items);
        self::assertInstanceOf(BoxItem::class, $items[0]);
        self::assertSame(10.0, $items[0]->widthPt());
    }
}
