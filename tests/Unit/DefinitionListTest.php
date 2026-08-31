<?php

declare(strict_types=1);

namespace Pdf\Tests\Unit;

use Pdf\Node\DefinitionList;
use Pdf\Node\Paragraph;
use Pdf\Node\Table;
use Pdf\Style\ColumnWidth;
use Pdf\Style\StylePatch;
use Pdf\Text\InlineSequence;
use PHPUnit\Framework\TestCase;

final class DefinitionListTest extends TestCase
{
    public function test_a_term_body_map_becomes_one_borderless_table_row_per_pair(): void
    {
        $table = (new DefinitionList(['Term' => 'Body', 'Other' => 'Second']))->body();

        self::assertInstanceOf(Table::class, $table);
        self::assertSame(0.0, $table->borderWidthPt);
        self::assertCount(2, $table->rows);
        self::assertCount(2, $table->rows[0]->cells);
    }

    public function test_the_term_column_defaults_to_auto_and_takes_the_given_width(): void
    {
        $auto = (new DefinitionList(['A' => 'b']))->body();
        self::assertInstanceOf(Table::class, $auto);
        self::assertSame(ColumnWidth::KIND_AUTO, $auto->columns[0]->kind);

        $fixed = (new DefinitionList(['A' => 'b'], termWidth: ColumnWidth::fixed(80.0)))->body();
        self::assertInstanceOf(Table::class, $fixed);
        self::assertTrue($fixed->columns[0]->isFixed());
        self::assertSame(80.0, $fixed->columns[0]->value);
    }

    public function test_pair_form_accepts_inline_sequences_and_block_bodies(): void
    {
        $list = new DefinitionList([
            [InlineSequence::of('Rich term'), 'plain body'],
            ['Blocks', [new Paragraph('one'), new Paragraph('two')]],
        ]);

        $table = $list->body();
        self::assertInstanceOf(Table::class, $table);
        self::assertCount(2, $table->rows);
        // The block-bodied cell keeps both paragraphs.
        self::assertCount(2, $table->rows[1]->cells[1]->children);
    }

    public function test_term_and_body_styles_are_threaded_onto_the_cells(): void
    {
        $list = new DefinitionList(
            ['A' => 'b'],
            termStyle: new StylePatch(bold: true, italic: true),
            bodyStyle: new StylePatch(fontSizePt: 9.0),
        );

        $row = $list->body();
        self::assertInstanceOf(Table::class, $row);
        self::assertTrue($row->rows[0]->cells[0]->patch->italic);
        self::assertSame(9.0, $row->rows[0]->cells[1]->patch->fontSizePt);
    }

    public function test_a_generator_pair_body_is_materialised_into_block_children(): void
    {
        $generator = (static function () {
            yield new Paragraph('a');
            yield new Paragraph('b');
        })();

        $list = new DefinitionList([['Blocks', $generator]]);

        $table = $list->body();
        self::assertInstanceOf(Table::class, $table);
        self::assertCount(2, $table->rows[0]->cells[1]->children);
    }

    public function test_a_map_form_block_body_is_rejected_with_a_clear_message(): void
    {
        $this->expectException(\Pdf\Exception\PdfException::class);
        $this->expectExceptionMessage('pair form');

        new DefinitionList(['Term' => [new Paragraph('x')]]);
    }

    public function test_an_empty_definition_list_is_rejected(): void
    {
        $this->expectException(\Pdf\Exception\PdfException::class);

        new DefinitionList([]);
    }
}
