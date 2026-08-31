<?php

declare(strict_types=1);

namespace Pdf\Tests\Functional;

use Pdf\Builder\DataTable;
use Pdf\Builder\Total;
use Pdf\Color\Color;
use Pdf\Document;
use Pdf\Style\ColumnWidth;
use Pdf\Style\TextAlign;
use Pdf\Tests\Support\Golden;
use Pdf\Tests\Support\Pdf;
use PHPUnit\Framework\TestCase;

final class DataTableRenderTest extends TestCase
{
    /** @return list<array<string, mixed>> */
    private function orders(): array
    {
        return [
            ['region' => 'Americas', 'account' => 'Northwind', 'seats' => 120, 'mrr' => 5400.0],
            ['region' => 'Americas', 'account' => 'Contoso', 'seats' => 40, 'mrr' => 1800.0],
            ['region' => 'EMEA', 'account' => 'Tailspin', 'seats' => 220, 'mrr' => 9900.0],
            ['region' => 'EMEA', 'account' => 'Wingtip', 'seats' => 60, 'mrr' => 2700.0],
        ];
    }

    private function table(): DataTable
    {
        $money = static fn (mixed $v): string => '$' . number_format((float) $v, 0);

        return DataTable::of($this->orders())
            ->column('account', 'Account')
            ->column('seats', 'Seats', TextAlign::Right, ColumnWidth::fixed(60.0))
            ->column('mrr', 'MRR', TextAlign::Right, ColumnWidth::fixed(80.0), $money)
            ->groupBy('region')
            ->totals([
                'account' => Total::label('Subtotal'),
                'seats' => Total::sum(),
                'mrr' => Total::sum(),
            ])
            ->headerBackground(Color::rgb(230, 236, 245));
    }

    public function test_renders_group_headers_subtotals_and_a_grand_total(): void
    {
        $pdf = Document::create()
            ->using(Pdf::deterministicRenderer())
            ->page(fn ($p) => $p->add($this->table()->build()))
            ->toString();

        $content = Pdf::contentText($pdf);

        self::assertStringContainsString('(Account) Tj', $content);
        self::assertStringContainsString('(Americas) Tj', $content);
        self::assertStringContainsString('(EMEA) Tj', $content);
        // Two subtotals + one grand total => "Subtotal" three times.
        self::assertSame(3, substr_count($content, '(Subtotal) Tj'));
        // Grand total: 120 + 40 + 220 + 60 = 440 seats, $19,800 MRR.
        self::assertStringContainsString('(440) Tj', $content);
        self::assertStringContainsString('($19,800) Tj', $content);
    }

    public function test_data_table_document_is_byte_stable(): void
    {
        $pdf = Document::create()
            ->using(Pdf::deterministicRenderer())
            ->meta(fn ($m) => $m->title('Subscriptions by region'))
            ->page(function ($p): void {
                $p->heading(1, 'Subscriptions by region');
                $p->add($this->table()->build());
            })
            ->toString();

        Golden::assert('data-table.pdf', $pdf);
    }
}
