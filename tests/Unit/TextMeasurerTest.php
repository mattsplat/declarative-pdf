<?php

declare(strict_types=1);

namespace Pdf\Tests\Unit;

use Pdf\Font\FontStyle;
use Pdf\Style\Style;
use Pdf\Style\StylePatch;
use Pdf\Text\TextMeasurer;
use PHPUnit\Framework\TestCase;

final class TextMeasurerTest extends TestCase
{
    public function test_width_matches_hand_computed_helvetica_advances(): void
    {
        $measurer = TextMeasurer::withBundledFonts();

        // helvetica.json advances: D722 E667 T611 A667 I278 L556 = 3501 /1000em
        self::assertEqualsWithDelta(
            35.01,
            $measurer->widthOf('DETAIL', 'Helvetica', FontStyle::Regular, 10.0),
            1e-9,
        );

        // helveticab.json advances: D722 E667 T611 A722 I278 L611 = 3611 /1000em
        self::assertEqualsWithDelta(
            36.11,
            $measurer->widthOf('DETAIL', 'Helvetica', FontStyle::Bold, 10.0),
            1e-9,
        );
    }

    public function test_width_from_a_style_reads_family_style_and_size_off_it(): void
    {
        $style = (new StylePatch(bold: true, fontSizePt: 10.0))->applyTo(Style::default());

        self::assertEqualsWithDelta(
            36.11,
            TextMeasurer::withBundledFonts()->width('DETAIL', $style),
            1e-9,
        );
    }

    public function test_empty_string_has_zero_width(): void
    {
        self::assertSame(0.0, TextMeasurer::withBundledFonts()->width('', Style::default()));
    }
}
