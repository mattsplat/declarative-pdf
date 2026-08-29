<?php

declare(strict_types=1);

namespace Pdf\Tests\Unit;

use Pdf\Font\FontFace;
use Pdf\Font\FontStyle;
use Pdf\Style\Style;
use Pdf\Style\StylePatch;
use Pdf\Style\Stylesheet;
use PHPUnit\Framework\TestCase;

final class StylePatchTest extends TestCase
{
    public function test_the_bold_shorthand_resolves_to_weight_700(): void
    {
        $bold = (new StylePatch(bold: true))->applyTo(Style::default());
        $weighted = (new StylePatch(weight: 700))->applyTo(Style::default());

        self::assertEquals($weighted, $bold);
        self::assertTrue($bold->fontFace->equals(FontFace::bold()));
    }

    public function test_an_explicit_weight_wins_over_the_bold_shorthand(): void
    {
        $out = (new StylePatch(weight: 300, bold: true))->applyTo(Style::default());

        self::assertSame(300, $out->fontFace->weight);
    }

    public function test_italic_alone_keeps_the_inherited_weight(): void
    {
        $base = (new StylePatch(weight: 600))->applyTo(Style::default());
        $out = (new StylePatch(italic: true))->applyTo($base);

        self::assertTrue($out->fontFace->equals(new FontFace(600, true)));
    }

    public function test_the_legacy_font_style_field_still_selects_a_cut(): void
    {
        $out = (new StylePatch(fontStyle: FontStyle::BoldItalic))->applyTo(Style::default());

        self::assertTrue($out->fontFace->equals(new FontFace(700, true)));
    }

    public function test_font_size_scale_multiplies_the_inherited_size(): void
    {
        $base = Style::default(); // 12pt
        $scaled = (new StylePatch(fontSizeScale: 0.5))->applyTo($base);

        self::assertSame(6.0, $scaled->fontSizePt);
    }

    public function test_explicit_size_wins_over_scale(): void
    {
        $out = (new StylePatch(fontSizePt: 20.0, fontSizeScale: 0.5))->applyTo(Style::default());

        self::assertSame(20.0, $out->fontSizePt);
    }

    public function test_superscript_is_smaller_and_raised(): void
    {
        $sup = StylePatch::superscript()->applyTo(Style::default());

        self::assertLessThan(12.0, $sup->fontSizePt);
        self::assertGreaterThan(0.0, $sup->baselineShift);
    }

    public function test_decorations_inherit_but_reset_block_properties_keeps_them(): void
    {
        $underlined = (new StylePatch(underline: true))->applyTo(Style::default());

        self::assertTrue($underlined->underline);
        self::assertTrue($underlined->resetBlockProperties()->underline);
    }

    public function test_stylesheet_patch_merges_and_later_selectors_win(): void
    {
        $sheet = (new Stylesheet())
            ->paragraph(new StylePatch(color: \Pdf\Color\Color::gray(80), lineHeight: 1.4))
            ->set('lead', new StylePatch(color: \Pdf\Color\Color::black()));

        $merged = $sheet->patchFor('paragraph', 'lead');

        self::assertTrue($merged->color?->equals(\Pdf\Color\Color::black()));
        self::assertSame(1.4, $merged->lineHeight);
    }

    public function test_stylesheet_returns_empty_patch_for_unknown_selectors(): void
    {
        $patch = (new Stylesheet())->patchFor('nope');
        $out = $patch->applyTo(Style::default());

        self::assertEquals(Style::default(), $out);
    }

    public function test_is_empty_is_true_only_when_every_field_is_null(): void
    {
        self::assertTrue((new StylePatch())->isEmpty());
        self::assertTrue(StylePatch::none()->isEmpty());

        // A field set to a falsy-but-not-null value is still an override.
        self::assertFalse((new StylePatch(bold: false))->isEmpty());
        self::assertFalse((new StylePatch(keepTogether: false))->isEmpty());
        self::assertFalse((new StylePatch(fontSizePt: 0.0))->isEmpty());
        self::assertFalse((new StylePatch(fontFamily: ''))->isEmpty());
        self::assertFalse((new StylePatch(underline: false))->isEmpty());
    }
}
