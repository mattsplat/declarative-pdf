<?php

declare(strict_types=1);

namespace Pdf\Tests\Unit;

use Pdf\Node\Paragraph;
use Pdf\Node\Row;
use Pdf\Node\Table;
use Pdf\Style\ColumnWidth;
use Pdf\Style\VerticalAlign;
use PHPUnit\Framework\TestCase;

final class RowTest extends TestCase
{
    public function test_children_become_one_borderless_table_row_with_gap_columns_between(): void
    {
        $row = (new Row([new Paragraph('a'), new Paragraph('b'), new Paragraph('c')], gapPt: 6.0))->body();

        self::assertInstanceOf(Table::class, $row);
        self::assertSame(0.0, $row->borderWidthPt);
        self::assertCount(1, $row->rows);
        // three content cells + two interleaved spacer cells
        self::assertCount(5, $row->rows[0]->cells);
        self::assertTrue($row->columns[1]->isFixed());
        self::assertSame(6.0, $row->columns[1]->value);
    }

    public function test_a_zero_gap_omits_the_spacer_columns(): void
    {
        $row = (new Row([new Paragraph('a'), new Paragraph('b')], gapPt: 0.0))->body();

        self::assertInstanceOf(Table::class, $row);
        self::assertCount(2, $row->rows[0]->cells);
    }

    public function test_vertical_align_is_threaded_onto_every_content_cell(): void
    {
        $row = (new Row([new Paragraph('a'), new Paragraph('b')], align: VerticalAlign::Bottom))->body();

        self::assertInstanceOf(Table::class, $row);
        self::assertSame(VerticalAlign::Bottom, $row->rows[0]->cells[0]->verticalAlign);
        self::assertSame(VerticalAlign::Bottom, $row->rows[0]->cells[2]->verticalAlign);
    }

    public function test_per_child_widths_apply_by_index_others_stay_auto(): void
    {
        $row = (new Row(
            [new Paragraph('a'), new Paragraph('b')],
            gapPt: 0.0,
            widths: [1 => ColumnWidth::fraction(1.0)],
        ))->body();

        self::assertInstanceOf(Table::class, $row);
        self::assertSame(ColumnWidth::KIND_AUTO, $row->columns[0]->kind);
        self::assertTrue($row->columns[1]->isFraction());
    }

    public function test_an_empty_row_is_rejected(): void
    {
        $this->expectException(\Pdf\Exception\PdfException::class);

        new Row([]);
    }
}
