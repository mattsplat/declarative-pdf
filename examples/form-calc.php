<?php

declare(strict_types=1);

require __DIR__ . '/../vendor/autoload.php';

use Pdf\Color\Color;
use Pdf\Document;
use Pdf\Interactive\Js;
use Pdf\Node\PushButton;
use Pdf\Node\TextField;
use Pdf\Style\StylePatch;
use Pdf\Style\TextAlign;

/*
 * A calculating invoice form. The line totals, subtotal, tax and grand total
 * recompute themselves — but ONLY in Adobe Acrobat / Reader (and mostly Foxit).
 * In Chrome, Preview or Firefox the same form is still a clean, fillable,
 * printable document; the computed fields just start blank.
 */

$rows = ['a', 'b', 'c'];
$money = fn (): Js => Js::formatCurrency(2, '$');
$right = new StylePatch(align: TextAlign::Right);

Document::create()
    ->meta(fn ($m) => $m->title('Invoice'))
    ->script('recalc', 'function recalc() { this.calculateNow(); }')
    ->page(function ($p) use ($rows, $money, $right): void {
        $p->heading(1, 'Invoice');
        $p->paragraph(
            'Open in Adobe Acrobat or Reader for automatic totals.',
            new StylePatch(color: Color::gray(110), spaceAfterPt: 10.0),
        );

        foreach ($rows as $i => $row) {
            $n = $i + 1;
            $p->paragraph("Line {$n}", new StylePatch(fontSizePt: 10.0, spaceAfterPt: 2.0));
            $p->field(new TextField(name: "line.{$row}.desc", label: 'Description', widthPt: 300.0));
            $p->field(new TextField(
                name: "line.{$row}.qty",
                label: 'Quantity',
                value: '0',
                widthPt: 90.0,
                align: TextAlign::Right,
                format: Js::formatNumber(0),
            ));
            $p->field(new TextField(
                name: "line.{$row}.price",
                label: 'Unit price',
                value: '0',
                widthPt: 110.0,
                align: TextAlign::Right,
                format: $money(),
            ));
            $p->field(new TextField(
                name: "line.{$row}.total",
                label: 'Line total',
                widthPt: 110.0,
                align: TextAlign::Right,
                readOnly: true,
                calculate: Js::product("line.{$row}.qty", "line.{$row}.price"),
                format: $money(),
            ));
        }

        $p->rule(0.75, Color::gray(160));

        $p->field(new TextField(
            name: 'subtotal',
            label: 'Subtotal',
            widthPt: 120.0,
            align: TextAlign::Right,
            readOnly: true,
            calculate: Js::sum('line.a.total', 'line.b.total', 'line.c.total'),
            format: $money(),
        ));
        $p->field(new TextField(
            name: 'tax',
            label: 'Tax (20%)',
            widthPt: 120.0,
            align: TextAlign::Right,
            readOnly: true,
            calculate: Js::raw('event.value = (Number(this.getField("subtotal").value) * 0.2).toFixed(2);'),
            format: $money(),
        ));
        $p->field(new TextField(
            name: 'total',
            label: 'Total due',
            widthPt: 120.0,
            align: TextAlign::Right,
            readOnly: true,
            calculate: Js::sum('subtotal', 'tax'),
            format: $money(),
            validate: Js::validateRange(0.0, null, 'The total cannot be negative.'),
        ));

        $p->spacer(4);
        $p->field(PushButton::action('recalc', 'Recalculate', Js::raw('recalc();')));
        $p->field(PushButton::reset('reset', 'Clear invoice'));
    })
    ->save(__DIR__ . '/form-calc.pdf');

echo "Wrote " . __DIR__ . "/form-calc.pdf\n";
