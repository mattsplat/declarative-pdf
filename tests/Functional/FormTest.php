<?php

declare(strict_types=1);

namespace Pdf\Tests\Functional;

use Pdf\Document;
use Pdf\Interactive\SubmitFormat;
use Pdf\Node\Checkbox;
use Pdf\Node\Dropdown;
use Pdf\Node\ListBox;
use Pdf\Node\PushButton;
use Pdf\Node\RadioGroup;
use Pdf\Node\SignatureField;
use Pdf\Node\TextField;
use Pdf\Tests\Support\Golden;
use Pdf\Tests\Support\Pdf;
use PHPUnit\Framework\TestCase;

final class FormTest extends TestCase
{
    private function form(): string
    {
        return Document::create()->using(Pdf::deterministicRenderer())
            ->meta(fn ($m) => $m->title('Registration form'))
            ->page(function ($p): void {
                $p->heading(1, 'Registration');
                $p->add(new TextField(name: 'full_name', value: 'Ada Lovelace', label: 'Full name'));
                $p->add(new TextField(name: 'notes', label: 'Notes', heightPt: 48.0, multiline: true));
                $p->add(new TextField(name: 'pin', label: 'PIN', maxLength: 4, comb: true, widthPt: 90.0));
                $p->add(new Checkbox(name: 'subscribe', checked: true, label: 'Email me product updates'));
                $p->add(new RadioGroup(
                    name: 'plan',
                    options: ['free' => 'Free', 'pro' => 'Pro', 'team' => 'Team'],
                    value: 'pro',
                    label: 'Plan',
                ));
                $p->add(new Dropdown(
                    name: 'country',
                    options: ['us' => 'United States', 'gb' => 'United Kingdom'],
                    value: 'gb',
                    label: 'Country',
                ));
                $p->add(new ListBox(
                    name: 'languages',
                    options: ['php' => 'PHP', 'js' => 'JavaScript', 'py' => 'Python'],
                    selected: 'php',
                    label: 'Languages',
                    multiSelect: true,
                ));
                $p->add(new SignatureField(name: 'signature', label: 'Signature'));
                $p->add(PushButton::submit('submit', 'Submit', 'https://example.com/register', SubmitFormat::Fdf));
                $p->add(PushButton::reset('reset', 'Reset'));
            })
            ->toString();
    }

    public function test_catalog_references_an_acroform_with_every_field(): void
    {
        $pdf = $this->form();

        self::assertMatchesRegularExpression('#/Type /Catalog\n/Pages 1 0 R\n/AcroForm \d+ 0 R#', $pdf);
        self::assertStringContainsString('/DR <</Font <<', $pdf);
        self::assertMatchesRegularExpression('#/DA \(/F\d 0 Tf 0 g\)#', $pdf);
        self::assertStringContainsString('/SigFlags 3', $pdf);

        // One /Fields entry per field node (radio group counts once).
        self::assertSame(1, preg_match('#/Fields \[([\d 0R]+)\]#', $pdf, $m));
        self::assertCount(10, array_filter(explode('0 R', trim($m[1]))));
    }

    public function test_every_widget_is_a_printable_widget_annotation_with_an_appearance(): void
    {
        $pdf = $this->form();

        self::assertSame(12, substr_count($pdf, '/Subtype /Widget'));
        self::assertStringContainsString('/FT /Tx', $pdf);
        self::assertStringContainsString('/FT /Btn', $pdf);
        self::assertStringContainsString('/FT /Ch', $pdf);
        self::assertStringContainsString('/FT /Sig', $pdf);
        self::assertStringContainsString('/Ff 25165824', $pdf); // comb: Comb | DoNotScroll
        self::assertStringContainsString('/A <</S /SubmitForm', $pdf);
        self::assertStringContainsString('/A <</S /ResetForm>>', $pdf);

        // Radio: one parent /Kids field plus one widget per option, each with /AP /N states.
        self::assertStringContainsString('/Kids [', $pdf);
        self::assertStringContainsString('/AS /pro', $pdf);
    }

    public function test_widget_rect_uses_the_shared_page_flip(): void
    {
        $pdf = Document::create()->using(Pdf::deterministicRenderer())
            ->page(fn ($p) => $p->add(new TextField(name: 'x', widthPt: 100.0, heightPt: 20.0)))
            ->toString();

        // Field lands at the very top of the A4 content box (page 841.89pt tall,
        // 28.35pt margin, first-child margin suppressed): top y = 28.35, so the
        // 20pt-tall widget's /Rect is [28.35 841.89-48.35 128.35 841.89-28.35].
        self::assertSame(1, preg_match('#/Rect \[28\.35 (\d+\.\d+) 128\.35 (\d+\.\d+)\]#', $pdf, $m));
        self::assertEqualsWithDelta(793.54, (float) $m[1], 0.5);
        self::assertEqualsWithDelta(813.54, (float) $m[2], 0.5);
    }

    public function test_a_field_placed_in_an_absolute_area_still_becomes_a_widget(): void
    {
        $pdf = Document::create()->using(Pdf::deterministicRenderer())
            ->page(fn ($p) => $p
                ->place(50.0, 60.0, 200.0, 40.0, [new TextField(name: 'placed', widthPt: 180.0, heightPt: 18.0)]))
            ->toString();

        self::assertStringContainsString('/AcroForm', $pdf);
        self::assertStringContainsString('/T (placed)', $pdf);
        self::assertSame(1, substr_count($pdf, '/Subtype /Widget'));
    }

    public function test_a_choice_field_pins_its_da_font_size_from_the_patch(): void
    {
        $pdf = Document::create()->using(Pdf::deterministicRenderer())
            ->page(fn ($p) => $p->add(new ListBox(
                name: 'interests',
                options: ['a' => 'A', 'b' => 'B', 'c' => 'C'],
                heightPt: 120.0,
                patch: new \Pdf\Style\StylePatch(fontSizePt: 8.5),
            )))
            ->toString();

        self::assertMatchesRegularExpression('#/T \(interests\)\n[^>]*/DA \(/F\d 8\.5 Tf#', $pdf);
    }

    public function test_a_choice_field_without_a_patch_size_stays_auto_sized(): void
    {
        $pdf = Document::create()->using(Pdf::deterministicRenderer())
            ->page(fn ($p) => $p->add(new Dropdown(
                name: 'country',
                options: ['us' => 'United States'],
            )))
            ->toString();

        self::assertMatchesRegularExpression('#/T \(country\)\n[^>]*/DA \(/F\d 0 Tf#', $pdf);
    }

    public function test_a_document_without_fields_has_no_acroform(): void
    {
        $pdf = Document::create()->using(Pdf::deterministicRenderer())
            ->page(fn ($p) => $p->paragraph('plain'))
            ->toString();

        self::assertStringNotContainsString('/AcroForm', $pdf);
        self::assertStringContainsString('%PDF-1.3', $pdf);
    }

    public function test_form_golden(): void
    {
        Golden::assert('form.pdf', $this->form());
    }
}
