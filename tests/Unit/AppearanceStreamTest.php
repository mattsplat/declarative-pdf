<?php

declare(strict_types=1);

namespace Pdf\Tests\Unit;

use Pdf\Color\Color;
use Pdf\Interactive\AppearanceStream;
use Pdf\Interactive\FieldAppearance;
use Pdf\Interactive\FieldFlag;
use Pdf\Interactive\FieldSpec;
use Pdf\Interactive\FieldType;
use Pdf\Tests\Support\Fonts;
use PHPUnit\Framework\TestCase;

final class AppearanceStreamTest extends TestCase
{
    private function spec(FieldType $type, int $flags = 0, ?int $maxLength = null, ?string $value = null): FieldSpec
    {
        return new FieldSpec(
            $type,
            'f',
            $flags,
            new FieldAppearance(Fonts::helvetica(), 0.0, Color::black(), Color::gray(128), Color::white(), 1.0, 0),
            maxLength: $maxLength,
            value: $value,
        );
    }

    public function test_auto_font_size_fills_the_widget_height_up_to_twelve_points(): void
    {
        self::assertSame(12.0, AppearanceStream::resolveFontSize(0.0, 40.0, false));
        self::assertSame(8.0, AppearanceStream::resolveFontSize(0.0, 12.0, false));
        self::assertSame(10.0, AppearanceStream::resolveFontSize(0.0, 200.0, true));
        self::assertSame(9.0, AppearanceStream::resolveFontSize(9.0, 40.0, false));
    }

    public function test_text_field_appearance_frames_the_box_and_clips_the_value(): void
    {
        $ap = AppearanceStream::textField(100.0, 20.0, $this->spec(FieldType::Text, value: 'Hi'), 'Hi');

        self::assertStringContainsString('0 0 100 20 re f', $ap);          // background
        self::assertStringContainsString('0.5 0.5 99 19 re S', $ap);        // inset border stroke
        self::assertStringStartsWith('q', $ap);
        self::assertStringContainsString('/Tx BMC', $ap);
        self::assertStringContainsString('re W n', $ap);                    // clip rectangle
        self::assertStringContainsString('(Hi) Tj', $ap);
        self::assertStringContainsString('EMC', $ap);
    }

    public function test_comb_text_places_one_glyph_per_cell(): void
    {
        $spec = $this->spec(FieldType::Text, FieldFlag::COMB, maxLength: 4, value: 'AB');
        $ap = AppearanceStream::textField(80.0, 20.0, $spec, 'AB');

        // Two glyphs, drawn one at a time (…) Tj, and no more than maxLength.
        self::assertSame(2, substr_count($ap, ') Tj'));
        self::assertStringContainsString('(A) Tj', $ap);
        self::assertStringContainsString('(B) Tj', $ap);
    }

    public function test_toggle_on_state_paints_a_marker_off_state_only_the_frame(): void
    {
        $spec = $this->spec(FieldType::Checkbox);
        $off = AppearanceStream::toggle(14.0, 14.0, $spec, false, 'check');
        $on = AppearanceStream::toggle(14.0, 14.0, $spec, true, 'check');

        self::assertStringNotContainsString(' l', $off); // frame only uses `re S` / `re f`
        self::assertStringContainsString(' m', $on);
        self::assertStringContainsString(' l', $on);
        self::assertStringContainsString('S', $on);
    }

    public function test_radio_on_state_is_a_filled_dot(): void
    {
        $on = AppearanceStream::toggle(16.0, 16.0, $this->spec(FieldType::Radio), true, 'dot');

        self::assertStringContainsString(' c', $on);   // bezier arcs
        self::assertStringContainsString("f\n", $on);  // filled
    }

    public function test_push_button_centres_its_caption(): void
    {
        $spec = new FieldSpec(
            FieldType::PushButton,
            'b',
            FieldFlag::PUSHBUTTON,
            new FieldAppearance(Fonts::helvetica(), 0.0, Color::black(), Color::gray(110), Color::gray(224), 1.0, 1),
            buttonLabel: 'Submit',
        );
        $ap = AppearanceStream::pushButton(120.0, 24.0, $spec);

        self::assertStringContainsString('(Submit) Tj', $ap);
        self::assertSame(1, preg_match('/([\d.]+) [\d.]+ Td/', $ap, $m));
        self::assertGreaterThan(2.0, (float) $m[1]); // not flush left
    }
}
