<?php

declare(strict_types=1);

namespace Pdf\Tests\Unit;

use Pdf\Builder\DataTable;
use Pdf\Builder\Total;
use Pdf\Exception\PdfException;
use Pdf\Node\Paragraph;
use Pdf\Node\Table;
use Pdf\Node\TableCell;
use Pdf\Style\TextAlign;
use PHPUnit\Framework\TestCase;

final class DataTableTest extends TestCase
{
    /** @return list<array<string, mixed>> */
    private function sales(): array
    {
        return [
            ['region' => 'North', 'rep' => 'Ada', 'units' => 3, 'revenue' => 1200.0],
            ['region' => 'North', 'rep' => 'Bo', 'units' => 5, 'revenue' => 800.0],
            ['region' => 'South', 'rep' => 'Cy', 'units' => 2, 'revenue' => 450.0],
        ];
    }

    /** @param list<mixed> $cells */
    private function textCells(TableCell ...$cells): array
    {
        return array_map(
            static function (TableCell $cell): string {
                $child = $cell->children[0];
                self::assertInstanceOf(Paragraph::class, $child);

                return $child->content->plainText();
            },
            $cells,
        );
    }

    public function test_columns_map_to_header_and_body_cells_in_order(): void
    {
        $table = DataTable::of($this->sales())
            ->column('region', 'Region')
            ->column('units', 'Units')
            ->build();

        self::assertInstanceOf(Table::class, $table);
        self::assertSame(['Region', 'Units'], $this->textCells(...$table->rows[0]->cells));
        self::assertSame(['North', '3'], $this->textCells(...$table->rows[1]->cells));
        self::assertSame(['South', '2'], $this->textCells(...$table->rows[3]->cells));
    }

    public function test_a_formatter_maps_the_raw_value_to_a_display_string(): void
    {
        $table = DataTable::of($this->sales())
            ->column('revenue', 'Revenue', TextAlign::Right, format: static fn (mixed $v): string => '$' . number_format((float) $v))
            ->build();

        self::assertSame(['$1,200'], $this->textCells($table->rows[1]->cells[0]));
        self::assertSame(TextAlign::Right, $table->rows[1]->cells[0]->patch->align);
    }

    public function test_null_and_missing_values_render_empty_without_a_formatter(): void
    {
        $table = DataTable::of([['a' => null]])
            ->column('a', 'A')
            ->column('missing', 'B')
            ->build();

        self::assertSame(['', ''], $this->textCells(...$table->rows[1]->cells));
    }

    public function test_sum_and_count_totals_are_correct(): void
    {
        $table = DataTable::of($this->sales())
            ->column('rep', 'Rep')
            ->column('units', 'Units')
            ->column('revenue', 'Revenue')
            ->totals([
                'rep' => Total::label('Total'),
                'units' => Total::count(),
                'revenue' => Total::sum(),
            ])
            ->build();

        $totalRow = $table->rows[array_key_last($table->rows)];
        self::assertSame(['Total', '3', '2450'], $this->textCells(...$totalRow->cells));
        self::assertTrue($totalRow->cells[0]->patch->bold);
    }

    public function test_avg_operates_on_the_raw_value_and_skips_non_numeric(): void
    {
        $rows = [
            ['n' => 10],
            ['n' => 'not a number'],
            ['n' => '30'],
        ];

        $table = DataTable::of($rows)
            ->column('n', 'N')
            ->totals(['n' => Total::avg()])
            ->build();

        // (10 + 30) / 2 — the non-numeric row is skipped, not counted.
        $totalRow = $table->rows[array_key_last($table->rows)];
        self::assertSame(['20'], $this->textCells($totalRow->cells[0]));
    }

    public function test_avg_over_no_numeric_values_is_zero(): void
    {
        $table = DataTable::of([['n' => 'x'], ['n' => 'y']])
            ->column('n', 'N')
            ->totals(['n' => Total::avg()])
            ->build();

        $totalRow = $table->rows[array_key_last($table->rows)];
        self::assertSame(['0'], $this->textCells($totalRow->cells[0]));
    }

    public function test_sum_result_is_passed_through_the_column_formatter(): void
    {
        $table = DataTable::of($this->sales())
            ->column('revenue', 'Revenue', format: static fn (mixed $v): string => '$' . number_format((float) $v))
            ->totals(['revenue' => Total::sum()])
            ->build();

        $totalRow = $table->rows[array_key_last($table->rows)];
        self::assertSame(['$2,450'], $this->textCells($totalRow->cells[0]));
    }

    public function test_total_of_receives_the_row_list(): void
    {
        $table = DataTable::of($this->sales())
            ->column('rep', 'Rep')
            ->column('revenue', 'Revenue')
            ->totals([
                'revenue' => Total::of(static function (array $rows): string {
                    $max = 0.0;
                    foreach ($rows as $row) {
                        $max = max($max, (float) $row['revenue']);
                    }

                    return 'max ' . (int) $max;
                }),
            ])
            ->build();

        $totalRow = $table->rows[array_key_last($table->rows)];
        self::assertSame(['', 'max 1200'], $this->textCells(...$totalRow->cells));
    }

    public function test_group_by_emits_a_group_header_before_each_run(): void
    {
        $table = DataTable::of($this->sales())
            ->column('rep', 'Rep')
            ->column('units', 'Units')
            ->groupBy('region')
            ->build();

        // header, North header, Ada, Bo, South header, Cy
        self::assertCount(6, $table->rows);
        self::assertSame(['North'], $this->textCells($table->rows[1]->cells[0]));
        self::assertSame(2, $table->rows[1]->cells[0]->colspan);
        self::assertSame(['South'], $this->textCells($table->rows[4]->cells[0]));
    }

    public function test_group_header_callback_maps_the_key_value(): void
    {
        $table = DataTable::of($this->sales())
            ->column('rep', 'Rep')
            ->groupBy('region', static fn (mixed $v): string => strtoupper((string) $v) . ' region')
            ->build();

        self::assertSame(['NORTH region'], $this->textCells($table->rows[1]->cells[0]));
    }

    public function test_group_by_adds_a_subtotal_per_group_plus_a_grand_total(): void
    {
        $table = DataTable::of($this->sales())
            ->column('rep', 'Rep')
            ->column('units', 'Units')
            ->groupBy('region')
            ->totals([
                'rep' => Total::label('Subtotal'),
                'units' => Total::sum(),
            ])
            ->build();

        // 0 header
        // 1 North header, 2 Ada, 3 Bo, 4 North subtotal (8)
        // 5 South header, 6 Cy, 7 South subtotal (2)
        // 8 grand total (10)
        self::assertCount(9, $table->rows);
        self::assertSame(['Subtotal', '8'], $this->textCells(...$table->rows[4]->cells));
        self::assertSame(['Subtotal', '2'], $this->textCells(...$table->rows[7]->cells));
        self::assertSame(['Subtotal', '10'], $this->textCells(...$table->rows[8]->cells));
    }

    public function test_object_rows_are_read_via_public_properties(): void
    {
        $rows = [
            (object) ['name' => 'Widget', 'qty' => 4],
            (object) ['name' => 'Gadget', 'qty' => 6],
        ];

        $table = DataTable::of($rows)
            ->column('name', 'Name')
            ->column('qty', 'Qty')
            ->totals(['qty' => Total::sum()])
            ->build();

        self::assertSame(['Widget', '4'], $this->textCells(...$table->rows[1]->cells));
        $totalRow = $table->rows[array_key_last($table->rows)];
        self::assertSame(['', '10'], $this->textCells(...$totalRow->cells));
    }

    public function test_styling_passthrough_reaches_the_table_node(): void
    {
        $table = DataTable::of($this->sales())
            ->column('rep', 'Rep')
            ->headerRows(1)
            ->borderWidthPt(1.25)
            ->build();

        self::assertSame(1.25, $table->borderWidthPt);
        self::assertSame(1, $table->headerRows);
    }

    public function test_build_without_columns_throws(): void
    {
        $this->expectException(PdfException::class);

        DataTable::of($this->sales())->build();
    }

    public function test_totals_referencing_an_unknown_column_throws(): void
    {
        $this->expectException(PdfException::class);

        DataTable::of($this->sales())
            ->column('rep', 'Rep')
            ->totals(['nope' => Total::sum()])
            ->build();
    }
}
