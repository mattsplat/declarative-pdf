<?php

declare(strict_types=1);

namespace Pdf\Builder;

use Pdf\Color\Color;
use Pdf\Exception\PdfException;
use Pdf\Geometry\Edges;
use Pdf\Node\Table;
use Pdf\Node\TableCell;
use Pdf\Node\TableRow;
use Pdf\Style\ColumnWidth;
use Pdf\Style\StylePatch;
use Pdf\Style\TextAlign;

/**
 * Builds a {@see Table} from a row collection and a set of column specs:
 * a header row, per-column formatting and alignment, optional grouping with
 * group-header rows, and sum/avg/count total rows (a grand total for the whole
 * table, plus one subtotal per group when {@see self::groupBy()} is set).
 *
 * `DataTable` is a builder, not a node: call {@see self::build()} and add the
 * resulting {@see Table} to the page flow, a container, or a cell.
 *
 * Rows are read in the order given — {@see self::groupBy()} groups *consecutive*
 * rows that share a key, so sort the collection first if you need that. Object
 * rows are read via their public properties.
 *
 * @phpstan-type ColumnSpec array{
 *     key: string,
 *     header: string,
 *     align: TextAlign|null,
 *     width: ColumnWidth,
 *     format: \Closure|null,
 * }
 */
final class DataTable
{
    /** @var list<ColumnSpec> */
    private array $columns = [];

    private ?string $groupKey = null;

    private ?\Closure $groupHeader = null;

    /** @var array<string, Total> */
    private array $totals = [];

    private int $headerRows = 1;

    private float $borderWidthPt = 0.5;

    private Color $borderColor;

    private Edges $cellPaddingPt;

    private ?Color $headerBackground = null;

    /** @param iterable<array<string, mixed>|object> $rows */
    private function __construct(
        private readonly iterable $rows,
    ) {
        // Mirror the Table node's own defaults so an untouched builder is a no-op.
        $this->borderColor = new Color(0, 0, 0);
        $this->cellPaddingPt = new Edges(3.0, 4.0, 3.0, 4.0);
    }

    /** @param iterable<array<string, mixed>|object> $rows */
    public static function of(iterable $rows): self
    {
        return new self($rows);
    }

    /**
     * Append a column. `$format` maps the raw cell value to its display string;
     * without it a scalar is cast to string and `null` renders empty.
     *
     * @param (callable(mixed): string)|null $format
     */
    public function column(
        string $key,
        string $header,
        ?TextAlign $align = null,
        ?ColumnWidth $width = null,
        ?callable $format = null,
    ): self {
        $this->columns[] = [
            'key' => $key,
            'header' => $header,
            'align' => $align,
            'width' => $width ?? ColumnWidth::auto(),
            'format' => $format === null ? null : $format(...),
        ];

        return $this;
    }

    /**
     * Emit a group-header row before each run of consecutive rows sharing
     * `$key`. `$header` maps the key value to the header text (default: the
     * value cast to string).
     *
     * @param (callable(mixed): string)|null $header
     */
    public function groupBy(string $key, ?callable $header = null): self
    {
        $this->groupKey = $key;
        $this->groupHeader = $header === null ? null : $header(...);

        return $this;
    }

    /**
     * Define the total rows, keyed by column key. Any column without an entry
     * gets an empty cell in the total rows.
     *
     * @param array<string, Total> $spec
     */
    public function totals(array $spec): self
    {
        $this->totals = $spec;

        return $this;
    }

    /** How many leading rows the {@see Table} treats as a repeating header (default 1). */
    public function headerRows(int $count): self
    {
        if ($count < 0) {
            throw new PdfException('headerRows cannot be negative.');
        }

        $this->headerRows = $count;

        return $this;
    }

    public function borderWidthPt(float $widthPt): self
    {
        $this->borderWidthPt = $widthPt;

        return $this;
    }

    public function borderColor(Color $color): self
    {
        $this->borderColor = $color;

        return $this;
    }

    public function cellPaddingPt(Edges $padding): self
    {
        $this->cellPaddingPt = $padding;

        return $this;
    }

    public function headerBackground(Color $color): self
    {
        $this->headerBackground = $color;

        return $this;
    }

    public function build(): Table
    {
        if ($this->columns === []) {
            throw new PdfException('A DataTable needs at least one column.');
        }

        $columnKeys = array_column($this->columns, 'key');
        foreach (array_keys($this->totals) as $key) {
            if (!in_array($key, $columnKeys, true)) {
                throw new PdfException(sprintf('totals() references unknown column "%s".', $key));
            }
        }

        $rows = $this->materialiseRows();
        $tableRows = [$this->headerRow()];

        if ($this->groupKey !== null) {
            foreach ($this->groupRuns($rows) as [$groupValue, $groupRows]) {
                $tableRows[] = $this->groupHeaderRow($groupValue);
                foreach ($groupRows as $row) {
                    $tableRows[] = $this->bodyRow($row);
                }
                if ($this->totals !== []) {
                    $tableRows[] = $this->totalRow($groupRows);
                }
            }
        } else {
            foreach ($rows as $row) {
                $tableRows[] = $this->bodyRow($row);
            }
        }

        if ($this->totals !== []) {
            $tableRows[] = $this->totalRow($rows);
        }

        $widths = [];
        foreach ($this->columns as $column) {
            $widths[] = $column['width'];
        }

        return new Table(
            $tableRows,
            $widths,
            headerRows: $this->headerRows,
            borderWidthPt: $this->borderWidthPt,
            borderColor: $this->borderColor,
            cellPaddingPt: $this->cellPaddingPt,
            headerBackground: $this->headerBackground,
        );
    }

    /** @return list<array<string, mixed>> */
    private function materialiseRows(): array
    {
        $rows = [];
        foreach ($this->rows as $row) {
            $rows[] = is_array($row) ? $row : get_object_vars($row);
        }

        return $rows;
    }

    /**
     * @param list<array<string, mixed>> $rows
     * @return list<array{mixed, list<array<string, mixed>>}>
     */
    private function groupRuns(array $rows): array
    {
        $key = (string) $this->groupKey;
        $runs = [];

        foreach ($rows as $row) {
            $value = $row[$key] ?? null;
            $last = array_key_last($runs);

            if ($last === null || $runs[$last][0] !== $value) {
                $runs[] = [$value, [$row]];
            } else {
                $runs[$last][1][] = $row;
            }
        }

        return $runs;
    }

    private function headerRow(): TableRow
    {
        $cells = [];
        foreach ($this->columns as $column) {
            $cells[] = new TableCell(
                $column['header'],
                patch: new StylePatch(bold: true, align: $column['align']),
            );
        }

        return new TableRow($cells);
    }

    private function groupHeaderRow(mixed $value): TableRow
    {
        $text = $this->groupHeader !== null
            ? (string) ($this->groupHeader)($value)
            : $this->stringify($value);

        return new TableRow([
            new TableCell($text, colspan: count($this->columns), patch: new StylePatch(bold: true)),
        ]);
    }

    /** @param array<string, mixed> $row */
    private function bodyRow(array $row): TableRow
    {
        $cells = [];
        foreach ($this->columns as $column) {
            $cells[] = new TableCell(
                $this->formatValue($column, $row[$column['key']] ?? null),
                patch: new StylePatch(align: $column['align']),
            );
        }

        return new TableRow($cells);
    }

    /**
     * @param list<array<string, mixed>> $rows
     */
    private function totalRow(array $rows): TableRow
    {
        $cells = [];
        foreach ($this->columns as $column) {
            $total = $this->totals[$column['key']] ?? null;
            $cells[] = new TableCell(
                $total === null ? '' : $this->aggregate($total, $column, $rows),
                patch: new StylePatch(bold: true, align: $column['align']),
            );
        }

        return new TableRow($cells);
    }

    /**
     * @param ColumnSpec $column
     * @param list<array<string, mixed>> $rows
     */
    private function aggregate(Total $total, array $column, array $rows): string
    {
        return match ($total->kind) {
            Total::KIND_LABEL => $total->label,
            Total::KIND_COUNT => (string) count($rows),
            Total::KIND_CALLABLE => (string) ($total->fn)($rows),
            Total::KIND_SUM, Total::KIND_AVG => $this->formatValue(
                $column,
                $this->reduce($total->kind, $this->numbers($column['key'], $rows)),
            ),
            default => throw new PdfException('Unknown total kind: ' . $total->kind),
        };
    }

    /** @param list<int|float> $numbers */
    private function reduce(string $kind, array $numbers): int|float
    {
        $sum = array_sum($numbers);

        if ($kind === Total::KIND_AVG && $numbers !== []) {
            return $sum / count($numbers);
        }

        return $sum;
    }

    /**
     * @param list<array<string, mixed>> $rows
     * @return list<int|float>
     */
    private function numbers(string $key, array $rows): array
    {
        $numbers = [];
        foreach ($rows as $row) {
            $value = $row[$key] ?? null;
            if (is_int($value) || is_float($value)) {
                $numbers[] = $value;
            } elseif (is_string($value) && is_numeric($value)) {
                $numbers[] = $value + 0;
            }
        }

        return $numbers;
    }

    /**
     * @param ColumnSpec $column
     */
    private function formatValue(array $column, mixed $raw): string
    {
        if ($column['format'] !== null) {
            return (string) ($column['format'])($raw);
        }

        return $this->stringify($raw);
    }

    private function stringify(mixed $value): string
    {
        if ($value === null) {
            return '';
        }
        if (is_bool($value)) {
            return $value ? 'true' : 'false';
        }
        if (is_scalar($value) || $value instanceof \Stringable) {
            return (string) $value;
        }

        throw new PdfException(sprintf(
            'DataTable cannot render a value of type %s; give the column a formatter.',
            get_debug_type($value),
        ));
    }
}
