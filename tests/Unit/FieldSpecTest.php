<?php

declare(strict_types=1);

namespace Pdf\Tests\Unit;

use Pdf\Color\Color;
use Pdf\Interactive\FieldAppearance;
use Pdf\Interactive\FieldFlag;
use Pdf\Interactive\FieldSpec;
use Pdf\Interactive\FieldType;
use Pdf\Tests\Support\Fonts;
use PHPUnit\Framework\TestCase;

final class FieldSpecTest extends TestCase
{
    private function appearance(): FieldAppearance
    {
        return new FieldAppearance(Fonts::helvetica(), 0.0, Color::black(), Color::gray(128), Color::white(), 0.75, 0);
    }

    public function test_ft_names_map_several_kinds_onto_btn_and_ch(): void
    {
        self::assertSame('Tx', FieldType::Text->acroName());
        self::assertSame('Btn', FieldType::Checkbox->acroName());
        self::assertSame('Btn', FieldType::Radio->acroName());
        self::assertSame('Btn', FieldType::PushButton->acroName());
        self::assertSame('Ch', FieldType::Dropdown->acroName());
        self::assertSame('Ch', FieldType::ListBox->acroName());
        self::assertSame('Sig', FieldType::Signature->acroName());
    }

    public function test_flag_bits_match_the_pdf_spec_positions(): void
    {
        self::assertSame(1, FieldFlag::READ_ONLY);
        self::assertSame(2, FieldFlag::REQUIRED);
        self::assertSame(4096, FieldFlag::MULTILINE);
        self::assertSame(8192, FieldFlag::PASSWORD);
        self::assertSame(1 << 15, FieldFlag::RADIO);
        self::assertSame(1 << 16, FieldFlag::PUSHBUTTON);
        self::assertSame(1 << 17, FieldFlag::COMBO);
        self::assertSame(1 << 21, FieldFlag::MULTI_SELECT);
        self::assertSame(1 << 24, FieldFlag::COMB);
    }

    public function test_is_flag_set_reads_the_combined_ff_value(): void
    {
        $spec = new FieldSpec(
            FieldType::Text,
            'notes',
            FieldFlag::MULTILINE | FieldFlag::REQUIRED,
            $this->appearance(),
        );

        self::assertTrue($spec->isFlagSet(FieldFlag::MULTILINE));
        self::assertTrue($spec->isFlagSet(FieldFlag::REQUIRED));
        self::assertFalse($spec->isFlagSet(FieldFlag::PASSWORD));
    }

    public function test_is_comb_requires_a_text_field_with_a_positive_max_length_and_the_comb_flag(): void
    {
        $comb = new FieldSpec(FieldType::Text, 'pin', FieldFlag::COMB, $this->appearance(), maxLength: 4);
        self::assertTrue($comb->isComb());

        $noMax = new FieldSpec(FieldType::Text, 'pin', FieldFlag::COMB, $this->appearance());
        self::assertFalse($noMax->isComb());

        $noFlag = new FieldSpec(FieldType::Text, 'pin', 0, $this->appearance(), maxLength: 4);
        self::assertFalse($noFlag->isComb());
    }

    public function test_default_appearance_string_names_the_font_size_and_colour(): void
    {
        $spec = new FieldSpec(
            FieldType::Text,
            'x',
            0,
            new FieldAppearance(Fonts::helvetica(), 11.0, Color::rgb(10, 20, 30), null, null, 0.0, 0),
        );

        self::assertSame('/F1 11 Tf 0.039 0.078 0.118 rg', $spec->appearance->defaultAppearance());
    }
}
