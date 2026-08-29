<?php

declare(strict_types=1);

require __DIR__ . '/../vendor/autoload.php';

use Pdf\Color\Color;
use Pdf\Document;
use Pdf\Geometry\Edges;
use Pdf\Interactive\Js;
use Pdf\Node\Container;
use Pdf\Node\Paragraph;
use Pdf\Node\PushButton;
use Pdf\Node\TextField;
use Pdf\Style\StylePatch;
use Pdf\Style\TextAlign;

/*
 * A calculating invoice. Line totals, subtotal, discount, tax and the grand
 * total recompute themselves — but ONLY in Adobe Acrobat / Reader (and mostly
 * Foxit). In Chrome, Preview or Firefox the same form is still a clean,
 * fillable, printable document; the computed fields just start blank.
 *
 * The Js helper wraps Acrobat's AFSimple_Calculate / AFNumber_Format recipes;
 * Js::raw() is the escape hatch for anything else.
 */

$navy = Color::rgb(20, 34, 66);
$muted = Color::gray(110);
$rows = ['a', 'b', 'c', 'd'];
$money = fn (): Js => Js::formatCurrency(2, '$');
$right = TextAlign::Right;

$sectionRule = fn (string $title) => new Container(
    [new Paragraph(strtoupper($title), new StylePatch(bold: true, color: Color::white(), fontSizePt: 9.0, spaceAfterPt: 0.0))],
    new StylePatch(background: $navy, paddingPt: Edges::symmetric(3.0, 6.0), spaceBeforePt: 12.0, spaceAfterPt: 8.0),
);

Document::create()
    ->meta(fn ($m) => $m->title('Invoice')->subject('Calculating AcroForm'))
    ->script('init', 'function init() { this.calculateNow(); }')
    ->page(function ($p) use ($navy, $muted, $rows, $money, $right, $sectionRule): void {
        $p->heading(1, 'Invoice', new StylePatch(color: $navy));
        $p->paragraph(
            'Open in Adobe Acrobat or Reader for automatic totals. Everywhere else '
            . 'this is still a fillable, printable invoice.',
            new StylePatch(color: $muted, spaceAfterPt: 4.0),
        );

        $p->add($sectionRule('Bill to'));
        $p->field(new TextField(name: 'bill.name', label: 'Name'));
        $p->field(new TextField(name: 'bill.email', label: 'Email'));
        $p->field(new TextField(name: 'invoice.number', label: 'Invoice #', value: 'INV-0001', widthPt: 140.0));

        $p->add($sectionRule('Line items'));
        foreach ($rows as $i => $row) {
            $n = $i + 1;
            $p->paragraph("Line {$n}", new StylePatch(fontSizePt: 10.0, bold: true, spaceBeforePt: 4.0, spaceAfterPt: 2.0));
            $p->field(new TextField(name: "line.{$row}.desc", label: 'Description', widthPt: 320.0));
            $p->field(new TextField(
                name: "line.{$row}.qty", label: 'Qty', value: '0', widthPt: 80.0,
                align: $right, format: Js::formatNumber(0),
            ));
            $p->field(new TextField(
                name: "line.{$row}.price", label: 'Unit price', value: '0', widthPt: 110.0,
                align: $right, format: $money(),
            ));
            $p->field(new TextField(
                name: "line.{$row}.total", label: 'Line total', widthPt: 110.0,
                align: $right, readOnly: true,
                calculate: Js::product("line.{$row}.qty", "line.{$row}.price"),
                format: $money(),
            ));
        }

        $p->add($sectionRule('Totals'));
        $lineTotals = array_map(static fn (string $r): string => "line.{$r}.total", $rows);

        $p->field(new TextField(
            name: 'subtotal', label: 'Subtotal', widthPt: 130.0, align: $right, readOnly: true,
            calculate: Js::sum(...$lineTotals), format: $money(),
        ));
        $p->field(new TextField(
            name: 'discount.pct', label: 'Discount %', value: '0', widthPt: 90.0,
            align: $right, format: Js::formatNumber(0),
        ));
        $p->field(new TextField(
            name: 'discount.amt', label: 'Discount', widthPt: 130.0, align: $right, readOnly: true,
            calculate: Js::raw('event.value = (Number(this.getField("subtotal").value) '
                . '* Number(this.getField("discount.pct").value) / 100).toFixed(2);'),
            format: $money(),
        ));
        $p->field(new TextField(
            name: 'tax', label: 'Tax (20%)', widthPt: 130.0, align: $right, readOnly: true,
            calculate: Js::raw('event.value = ((Number(this.getField("subtotal").value) '
                . '- Number(this.getField("discount.amt").value)) * 0.2).toFixed(2);'),
            format: $money(),
        ));
        $p->field(new TextField(
            name: 'total', label: 'Total due', widthPt: 130.0, align: $right, readOnly: true,
            calculate: Js::raw('event.value = (Number(this.getField("subtotal").value) '
                . '- Number(this.getField("discount.amt").value) '
                . '+ Number(this.getField("tax").value)).toFixed(2);'),
            format: $money(),
            validate: Js::validateRange(0.0, null, 'The total cannot be negative.'),
        ));

        $p->spacer(6);
        $p->field(PushButton::action('recalc', 'Recalculate', Js::raw('init();')));
        $p->field(PushButton::reset('reset', 'Clear invoice'));
    })
    ->save(__DIR__ . '/form-calc.pdf');

echo 'Wrote ' . __DIR__ . "/form-calc.pdf\n";
