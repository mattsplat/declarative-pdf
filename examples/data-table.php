<?php

declare(strict_types=1);

require __DIR__ . '/../vendor/autoload.php';

use Pdf\Builder\DataTable;
use Pdf\Builder\Total;
use Pdf\Color\Color;
use Pdf\Document;
use Pdf\Layout\PageContext;
use Pdf\Node\Paragraph;
use Pdf\Style\ColumnWidth;
use Pdf\Style\StylePatch;
use Pdf\Style\TextAlign;

/*
 * The data-driven table builder: hand it a row collection and column specs and
 * it synthesises the header, the body, group headers, per-group subtotals and a
 * grand total. Compare examples/table.php, which hand-rolls the same shape.
 *
 * The rows here are ordered by region because groupBy() groups *consecutive*
 * rows — sort your collection to match the grouping you want.
 */

$navy = Color::rgb(22, 38, 74);

$orders = [
    ['region' => 'Americas', 'account' => 'Northwind Traders', 'seats' => 120, 'mrr' => 5400.0, 'renews' => '2026-02-01'],
    ['region' => 'Americas', 'account' => 'Contoso Ltd', 'seats' => 40, 'mrr' => 1800.0, 'renews' => '2026-03-15'],
    ['region' => 'Americas', 'account' => 'Fabrikam Inc', 'seats' => 15, 'mrr' => 720.0, 'renews' => '2026-01-20'],
    ['region' => 'EMEA', 'account' => 'Tailspin Toys', 'seats' => 220, 'mrr' => 9900.0, 'renews' => '2026-04-01'],
    ['region' => 'EMEA', 'account' => 'Wingtip GmbH', 'seats' => 60, 'mrr' => 2700.0, 'renews' => '2026-02-28'],
    ['region' => 'APAC', 'account' => 'Adventure Works', 'seats' => 35, 'mrr' => 1575.0, 'renews' => '2026-03-05'],
    ['region' => 'APAC', 'account' => 'Proseware KK', 'seats' => 18, 'mrr' => 810.0, 'renews' => '2026-05-12'],
];

$money = static fn (mixed $value): string => '$' . number_format((float) $value, 0);
$right = TextAlign::Right;
$earliest = static function (array $rows): string {
    $dates = array_column($rows, 'renews');
    sort($dates);

    return (string) ($dates[0] ?? '-');
};

$table = DataTable::of($orders)
    ->column('account', 'Account')
    ->column('seats', 'Seats', $right, ColumnWidth::fixed(58.0))
    ->column('mrr', 'MRR', $right, ColumnWidth::fixed(78.0), $money)
    ->column('renews', 'Renews', $right, ColumnWidth::fixed(78.0))
    ->groupBy('region', static fn (mixed $region): string => (string) $region . ' region')
    ->totals([
        // The first column labels itself ("Subtotal" per group, "Total" overall).
        'seats' => Total::sum(),
        'mrr' => Total::sum(),
        'renews' => Total::of($earliest),
    ])
    ->headerBackground(Color::rgb(230, 236, 245))
    ->borderColor(Color::gray(205));

Document::create()
    ->meta(fn ($m) => $m->title('Subscriptions by region')->subject('DataTable builder'))
    ->page(function ($p) use ($navy, $table): void {
        $p->footer(fn (PageContext $c) => new Paragraph(
            "Subscriptions   –   page {$c->pageNumber} / {$c->pageCount}",
            new StylePatch(fontSizePt: 8.0, color: Color::gray(120), align: TextAlign::Center, spaceAfterPt: 0.0),
        ));

        $p->heading(1, 'Subscriptions by region', new StylePatch(color: $navy));
        $p->paragraph(
            'One DataTable call: the MRR column is formatted as currency, rows are '
            . 'grouped by region with a subtotal after each group, and a grand total '
            . 'closes the table. sum/avg run on the raw numbers, so the currency '
            . 'formatter also renders the totals.',
            new StylePatch(spaceAfterPt: 8.0),
        );

        $p->dataTable($table);
    })
    ->save(__DIR__ . '/data-table.pdf');

echo 'Wrote ' . __DIR__ . "/data-table.pdf\n";
