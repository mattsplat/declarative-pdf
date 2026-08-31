<?php

declare(strict_types=1);

namespace Pdf\Tests\Unit;

use Pdf\Builder\DataTable;
use Pdf\Builder\Total;
use Pdf\Exception\PdfException;
use Pdf\Node\Paragraph;
use Pdf\Node\Table;
use Pdf\Node\TableCell;
use Pdf\Node\TableRow;
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

    private function text(TableCell $cell): string
    {
        $child = $cell->children[0];
        self::assertInstanceOf(Paragraph::class, $child);

        return $child->content->plainText();
    }

    /** @return list<string> */
    private function rowText(TableRow $row): array
    {
        return array_map($this->text(...), $row->cells);
    }

    public function test_columns_map_to_header_and_body_cells_in_order(): void
    {
        $table = DataTable::of($this->sales())
            ->column('region', 'Region')
            ->column('units', 'Units')
            ->build();

        self::assertInstanceOf(Table::class, $table);
        self::assertSame(['Region', 'Units'], $this->rowText($table->rows[0]));
        self::assertSame(['North', '3'], $this->rowText($table->rows[1]));
        self::assertSame(['South', '2'], $this->rowText($table->rows[3]));
    }

    public function test_a_formatter_maps_the_raw_value_to_a_display_string(): void
    {
        $table = DataTable::of($this->sales())
            ->column('revenue', 'Revenue', TextAlign::Right, format: static fn (mixed $v): string => '$' . number_format((float) $v))
            ->build();

        self::assertSame('$1,200', $this->text($table->rows[1]->cells[0]));
        self::assertSame(TextAlign::Right, $table->rows[1]->cells[0]->patch->align);
    }

    public function test_null_and_missing_values_render_empty_without_a_formatter(): void
    {
        $table = DataTable::of([['a' => null]])
            ->column('a', 'A')
            ->column('missing', 'B')
            ->build();

        self::assertSame(['', ''], $this->rowText($table->rows[1]));
    }

    public function test_stringable_objects_render_via_to_string(): void
    {
        $money = new class {
            public function __toString(): string
            {
                return 'EUR 5';
            }
        };

        $table = DataTable::of([['price' => $money]])
            ->column('price', 'Price')
            ->build();

        self::assertSame('EUR 5', $this->text($table->rows[1]->cells[0]));
    }

    public function test_a_non_stringable_value_without_a_formatter_throws(): void
    {
        $table = DataTable::of([['blob' => ['x' => 1]]])->column('blob', 'Blob');

        $this->expectException(PdfException::class);
        $table->build();
    }

    public function test_sum_and_count_totals_are_correct(): void
    {
        $table = DataTable::of($this->sales())
            ->column('rep', 'Rep')
            ->column('units', 'Units')
            ->column('revenue', 'Revenue')
            ->totals([
                'units' => Total::count(),
                'revenue' => Total::sum(),
            ])
            ->build();

        // Not grouped: the one total row is the grand total; column 0 auto-labels.
        self::assertSame(['Total', '3', '2450'], $this->rowText($table->rows[array_key_last($table->rows)]));
        self::assertTrue($table->rows[array_key_last($table->rows)]->cells[0]->patch->bold);
    }

    public function test_sum_handles_mixed_int_and_float_and_negative_values(): void
    {
        $table = DataTable::of([['n' => 10], ['n' => 2.5], ['n' => -4], ['n' => '1.5']])
            ->column('n', 'N')
            ->totals(['n' => Total::sum()])
            ->build();

        self::assertSame('10', $this->text($table->rows[array_key_last($table->rows)]->cells[0]));
    }

    public function test_avg_operates_on_the_raw_value_and_skips_non_numeric(): void
    {
        $table = DataTable::of([['n' => 10], ['n' => 'not a number'], ['n' => '30']])
            ->column('n', 'N')
            ->totals(['n' => Total::avg()])
            ->build();

        // (10 + 30) / 2 — the non-numeric row is skipped, not counted.
        self::assertSame('20', $this->text($table->rows[array_key_last($table->rows)]->cells[0]));
    }

    public function test_avg_over_no_numeric_values_is_zero(): void
    {
        $table = DataTable::of([['n' => 'x'], ['n' => 'y']])
            ->column('n', 'N')
            ->totals(['n' => Total::avg()])
            ->build();

        self::assertSame('0', $this->text($table->rows[array_key_last($table->rows)]->cells[0]));
    }

    public function test_sum_result_is_passed_through_the_column_formatter(): void
    {
        $table = DataTable::of($this->sales())
            ->column('revenue', 'Revenue', format: static fn (mixed $v): string => '$' . number_format((float) $v))
            ->totals(['revenue' => Total::sum()])
            ->build();

        self::assertSame('$2,450', $this->text($table->rows[array_key_last($table->rows)]->cells[0]));
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

        // Column 0 has no Total => it auto-labels the grand row.
        self::assertSame(['Total', 'max 1200'], $this->rowText($table->rows[array_key_last($table->rows)]));
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
        self::assertSame('North', $this->text($table->rows[1]->cells[0]));
        self::assertSame(2, $table->rows[1]->cells[0]->colspan);
        self::assertSame('South', $this->text($table->rows[4]->cells[0]));
    }

    public function test_group_by_groups_only_consecutive_runs(): void
    {
        $rows = [
            ['g' => 'A', 'n' => 1],
            ['g' => 'B', 'n' => 2],
            ['g' => 'A', 'n' => 3],
        ];

        $table = DataTable::of($rows)
            ->column('g', 'G')
            ->column('n', 'N')
            ->groupBy('g')
            ->totals(['n' => Total::sum()])
            ->build();

        // header + 3 groups x (group header + 1 row + subtotal) + grand total = 11
        self::assertCount(11, $table->rows);
        self::assertSame('A', $this->text($table->rows[1]->cells[0]));
        self::assertSame('B', $this->text($table->rows[4]->cells[0]));
        self::assertSame('A', $this->text($table->rows[7]->cells[0]));
        // The two "A" runs stay separate: subtotals 1 and 3, not a merged 4.
        self::assertSame(['Subtotal', '1'], $this->rowText($table->rows[3]));
        self::assertSame(['Subtotal', '3'], $this->rowText($table->rows[9]));
        self::assertSame(['Total', '6'], $this->rowText($table->rows[10]));
    }

    public function test_a_missing_group_key_falls_into_a_single_null_group(): void
    {
        $rows = [['name' => 'x'], ['name' => 'y', 'region' => 'East'], ['name' => 'z']];

        $table = DataTable::of($rows)
            ->column('name', 'Name')
            ->groupBy('region', static fn (mixed $v): string => $v === null ? 'Unassigned' : (string) $v)
            ->build();

        // header, [null] header, x, [East] header, y, [null] header, z
        self::assertCount(7, $table->rows);
        self::assertSame('Unassigned', $this->text($table->rows[1]->cells[0]));
        self::assertSame('East', $this->text($table->rows[3]->cells[0]));
        self::assertSame('Unassigned', $this->text($table->rows[5]->cells[0]));
    }

    public function test_group_header_callback_maps_the_key_value(): void
    {
        $table = DataTable::of($this->sales())
            ->column('rep', 'Rep')
            ->groupBy('region', static fn (mixed $v): string => strtoupper((string) $v) . ' region')
            ->build();

        self::assertSame('NORTH region', $this->text($table->rows[1]->cells[0]));
    }

    public function test_group_by_adds_a_subtotal_per_group_plus_a_distinct_grand_total(): void
    {
        $table = DataTable::of($this->sales())
            ->column('rep', 'Rep')
            ->column('units', 'Units')
            ->groupBy('region')
            ->totals(['units' => Total::sum()])
            ->build();

        // 0 header
        // 1 North header, 2 Ada, 3 Bo, 4 North subtotal (8)
        // 5 South header, 6 Cy, 7 South subtotal (2)
        // 8 grand total (10)
        self::assertCount(9, $table->rows);
        self::assertSame(['Subtotal', '8'], $this->rowText($table->rows[4]));
        self::assertSame(['Subtotal', '2'], $this->rowText($table->rows[7]));
        self::assertSame(['Total', '10'], $this->rowText($table->rows[8]));
    }

    public function test_a_label_total_is_relabelled_on_the_grand_row(): void
    {
        $table = DataTable::of($this->sales())
            ->column('rep', 'Rep')
            ->column('units', 'Units')
            ->groupBy('region')
            ->totals([
                'rep' => Total::label('By region'),
                'units' => Total::sum(),
            ])
            ->build();

        self::assertSame(['By region', '8'], $this->rowText($table->rows[4]));
        self::assertSame(['Total', '10'], $this->rowText($table->rows[8]));
    }

    public function test_grand_totals_override_drives_the_grand_row(): void
    {
        $table = DataTable::of($this->sales())
            ->column('rep', 'Rep')
            ->column('units', 'Units')
            ->column('revenue', 'Revenue')
            ->groupBy('region')
            ->totals(['units' => Total::sum()])
            ->grandTotals([
                'rep' => Total::label('Company'),
                'units' => Total::sum(),
                'revenue' => Total::sum(),
            ])
            ->build();

        $grand = $table->rows[array_key_last($table->rows)];
        self::assertSame(['Company', '10', '2450'], $this->rowText($grand));
        // The per-group subtotal keeps the smaller totals() spec (no revenue).
        self::assertSame(['Subtotal', '8', ''], $this->rowText($table->rows[4]));
    }

    public function test_a_single_group_does_not_repeat_its_subtotal_as_a_grand_total(): void
    {
        $rows = [
            ['region' => 'North', 'units' => 3],
            ['region' => 'North', 'units' => 5],
        ];

        $table = DataTable::of($rows)
            ->column('region', 'Region')
            ->column('units', 'Units')
            ->groupBy('region')
            ->totals(['units' => Total::sum()])
            ->build();

        // header, North header, r1, r2, North subtotal — and no grand total row.
        self::assertCount(5, $table->rows);
        self::assertSame(['Subtotal', '8'], $this->rowText($table->rows[4]));
    }

    public function test_a_single_group_still_gets_a_grand_total_when_grand_totals_is_explicit(): void
    {
        $rows = [
            ['region' => 'North', 'units' => 3],
            ['region' => 'North', 'units' => 5],
        ];

        $table = DataTable::of($rows)
            ->column('region', 'Region')
            ->column('units', 'Units')
            ->groupBy('region')
            ->totals(['units' => Total::sum()])
            ->grandTotals(['units' => Total::sum()])
            ->build();

        self::assertCount(6, $table->rows);
        self::assertSame(['Subtotal', '8'], $this->rowText($table->rows[4]));
        self::assertSame(['Total', '8'], $this->rowText($table->rows[5]));
    }

    public function test_empty_input_ungrouped(): void
    {
        $noTotals = DataTable::of([])->column('a', 'A')->build();
        self::assertCount(1, $noTotals->rows);
        self::assertSame(['A'], $this->rowText($noTotals->rows[0]));

        $withTotals = DataTable::of([])
            ->column('a', 'A')
            ->column('n', 'N')
            ->totals(['n' => Total::sum()])
            ->build();
        self::assertCount(2, $withTotals->rows);
        self::assertSame(['Total', '0'], $this->rowText($withTotals->rows[1]));
    }

    public function test_empty_input_grouped(): void
    {
        $noTotals = DataTable::of([])->column('a', 'A')->groupBy('a')->build();
        self::assertCount(1, $noTotals->rows);

        $withTotals = DataTable::of([])
            ->column('a', 'A')
            ->column('n', 'N')
            ->groupBy('a')
            ->totals(['n' => Total::sum()])
            ->build();
        // No groups => no subtotals and no grand total.
        self::assertCount(1, $withTotals->rows);
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

        self::assertSame(['Widget', '4'], $this->rowText($table->rows[1]));
        self::assertSame(['Total', '10'], $this->rowText($table->rows[array_key_last($table->rows)]));
    }

    public function test_styling_passthrough_reaches_the_table_node(): void
    {
        $table = DataTable::of($this->sales())
            ->column('rep', 'Rep')
            ->headerRows(0)
            ->borderWidthPt(1.25)
            ->build();

        self::assertSame(1.25, $table->borderWidthPt);
        self::assertSame(0, $table->headerRows);
    }

    public function test_header_rows_above_one_is_rejected(): void
    {
        $this->expectException(PdfException::class);

        DataTable::of($this->sales())->column('rep', 'Rep')->headerRows(2);
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

    public function test_grand_totals_referencing_an_unknown_column_throws(): void
    {
        $this->expectException(PdfException::class);

        DataTable::of($this->sales())
            ->column('rep', 'Rep')
            ->grandTotals(['nope' => Total::sum()])
            ->build();
    }
}
