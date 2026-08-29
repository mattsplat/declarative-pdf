<?php

declare(strict_types=1);

namespace Pdf\Tests\Functional;

use Pdf\Document;
use Pdf\Interactive\Js;
use Pdf\Node\PushButton;
use Pdf\Node\TextField;
use Pdf\Tests\Support\Golden;
use Pdf\Tests\Support\Pdf;
use PHPUnit\Framework\TestCase;

final class FormJavaScriptTest extends TestCase
{
    private function invoice(): string
    {
        return Document::create()->using(Pdf::deterministicRenderer())
            ->meta(fn ($m) => $m->title('Invoice'))
            ->script('greet', 'function greet() { app.alert("hi"); }')
            ->script('recalc', 'function recalc() { this.calculateNow(); }')
            ->page(function ($p): void {
                $p->heading(1, 'Invoice');
                $p->field(new TextField(name: 'qty', label: 'Quantity', value: '0'));
                $p->field(new TextField(name: 'price', label: 'Unit price', value: '0', format: Js::formatCurrency()));
                $p->field(new TextField(
                    name: 'line_total',
                    label: 'Line total',
                    readOnly: true,
                    calculate: Js::product('qty', 'price'),
                    format: Js::formatCurrency(),
                ));
                $p->field(new TextField(
                    name: 'total',
                    label: 'Total',
                    readOnly: true,
                    calculate: Js::sum('line_total'),
                    validate: Js::validateRange(0.0, null),
                ));
                $p->field(PushButton::action('recalc', 'Recalculate', Js::raw('recalc();')));
            })
            ->toString();
    }

    public function test_document_scripts_become_a_sorted_javascript_name_tree(): void
    {
        $pdf = $this->invoice();

        self::assertMatchesRegularExpression('#/AcroForm \d+ 0 R\n/Names \d+ 0 R#', $pdf);
        self::assertStringContainsString('/JavaScript <</Names [', $pdf);

        // Keys are emitted in sorted order: greet before recalc.
        self::assertSame(1, preg_match('/\(greet\) \d+ 0 R\n\(recalc\) \d+ 0 R/', $pdf));
        self::assertStringContainsString('<</S /JavaScript /JS (function greet\\(\\) { app.alert\\("hi"\\); })>>', $pdf);
    }

    public function test_calculated_fields_get_an_aa_dict_and_join_the_calc_order(): void
    {
        $pdf = $this->invoice();

        self::assertStringContainsString('/C <</S /JavaScript /JS (AFSimple_Calculate\\("PRD"', $pdf);
        self::assertStringContainsString('/V <</S /JavaScript', $pdf); // validate on `total`

        // /CO lists exactly the two calculated fields, in field order.
        self::assertSame(1, preg_match('#/CO \[(\d+ 0 R \d+ 0 R)\]#', $pdf, $m));
        self::assertCount(2, array_filter(explode('0 R', trim($m[1]))));
    }

    public function test_currency_format_adds_a_keystroke_and_a_format_action(): void
    {
        $pdf = $this->invoice();

        self::assertStringContainsString('/K <</S /JavaScript /JS (AFNumber_Keystroke\\(2, 0, 0, 0, "$", true\\);)>>', $pdf);
        self::assertStringContainsString('/F <</S /JavaScript /JS (AFNumber_Format\\(2, 0, 0, 0, "$", true\\);)>>', $pdf);
    }

    public function test_push_button_runs_its_javascript_action(): void
    {
        $pdf = $this->invoice();

        self::assertStringContainsString('/A <</S /JavaScript /JS (recalc\\(\\);)>>', $pdf);
    }

    public function test_a_form_without_js_still_has_no_names_entry(): void
    {
        $pdf = Document::create()->using(Pdf::deterministicRenderer())
            ->page(fn ($p) => $p->field(new TextField(name: 'x')))
            ->toString();

        self::assertStringContainsString('/AcroForm', $pdf);
        self::assertStringNotContainsString('/Names', $pdf);
        self::assertStringNotContainsString('/AA', $pdf);
        self::assertStringNotContainsString('/CO', $pdf);
    }

    public function test_invoice_golden(): void
    {
        Golden::assert('form-calc.pdf', $this->invoice());
    }
}
