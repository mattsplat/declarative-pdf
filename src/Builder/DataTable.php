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
 * group-header rows, and sum/avg/count total rows.
 *
 * `DataTable` is a builder, not a node: call {@see self::build()} and add the
 * resulting {@see Table} to the page flow, a container, or a cell — or hand the
 * builder itself to {@see PageBuilder::dataTable()}.
 *
 * Rows are read in the order given — {@see self::groupBy()} groups *consecutive*
 * rows that share a key, so sort the collection first if you need that. Object
 * rows are read via their public properties.
 *
 * Total rows: {@see self::totals()} defines a per-group **subtotal** row (when
 * grouping) and the whole-table **grand total** row. The first column of a total
 * row is labelled automatically — `"Subtotal"` / `"Total"` — unless the spec
 * gives that column its own {@see Total}. {@see self::grandTotals()} overrides
 * the grand row's spec; without it the grand row reuses the `totals` spec with
 * every {@see Total::label()} relabelled to `"Total"` so it never reads as one
 * more subtotal. A grand total that would just repeat a lone subtotal (one
 * group, no explicit `grandTotals`) is suppressed.
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

    /** @var array<string, Total>|null */
    private ?array $grandTotals = null;

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
     * value cast to string). A missing key reads as `null`, i.e. one group.
     *
     * With grouping the first row (the column header) is the only repeating
     * page header — see {@see self::headerRows()}.
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
     * Define the subtotal / grand-total rows, keyed by column key. A `sum` /
     * `avg` skips any value that is not `int|float` and not a numeric string;
     * see {@see Total}. A column with no entry gets an automatic label in its
     * first column and an empty cell elsewhere.
     *
     * @param array<string, Total> $spec
     */
    public function totals(array $spec): self
    {
        $this->totals = $spec;

        return $this;
    }

    /**
     * Override the whole-table grand-total row's spec (see {@see self::totals()}
     * for the shape). Without this the grand row derives from `totals()`.
     *
     * @param array<string, Total> $spec
     */
    public function grandTotals(array $spec): self
    {
        $this->grandTotals = $spec;

        return $this;
    }

    /**
     * How many leading rows the {@see Table} repeats on every page it spans.
     * The builder emits exactly one header row, so the only values are 1
     * (repeat it, the default) and 0 (don't).
     */
    public function headerRows(int $count): self
    {
        if ($count < 0 || $count > 1) {
            throw new PdfException('A DataTable has a single header row: headerRows must be 0 or 1.');
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

        $this->assertKnownColumns('totals', $this->totals);
        $this->assertKnownColumns('grandTotals', $this->grandTotals ?? []);

        $rows = $this->materialiseRows();
        $tableRows = [$this->headerRow()];

        $hasSubtotals = $this->groupKey !== null && $this->totals !== [];
        $hasGrand = $this->totals !== [] || $this->grandTotals !== null;

        if ($this->groupKey !== null) {
            $runs = $this->groupRuns($rows);
            foreach ($runs as [$groupValue, $groupRows]) {
                $tableRows[] = $this->groupHeaderRow($groupValue);
                foreach ($groupRows as $row) {
                    $tableRows[] = $this->bodyRow($row);
                }
                if ($hasSubtotals) {
                    $tableRows[] = $this->totalRow($this->totals, $groupRows, 'Subtotal');
                }
            }

            $redundant = $hasSubtotals && count($runs) <= 1 && $this->grandTotals === null;
            if ($hasGrand && !$redundant) {
                $tableRows[] = $this->totalRow($this->grandSpec(), $rows, 'Total');
            }
        } else {
            foreach ($rows as $row) {
                $tableRows[] = $this->bodyRow($row);
            }
            if ($hasGrand) {
                $tableRows[] = $this->totalRow($this->grandSpec(), $rows, 'Total');
            }
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

    /**
     * The grand-total row's spec: the explicit {@see self::grandTotals()} one,
     * or the `totals()` spec with every fixed label relabelled to `"Total"`.
     *
     * @return array<string, Total>
     */
    private function grandSpec(): array
    {
        if ($this->grandTotals !== null) {
            return $this->grandTotals;
        }

        $spec = [];
        foreach ($this->totals as $key => $total) {
            $spec[$key] = $total->kind === Total::KIND_LABEL ? Total::label('Total') : $total;
        }

        return $spec;
    }

    /** @param array<string, Total> $spec */
    private function assertKnownColumns(string $method, array $spec): void
    {
        $keys = array_column($this->columns, 'key');
        foreach (array_keys($spec) as $key) {
            if (!in_array($key, $keys, true)) {
                throw new PdfException(sprintf('%s() references unknown column "%s".', $method, $key));
            }
        }
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
     * @param array<string, Total> $spec
     * @param list<array<string, mixed>> $rows
     */
    private function totalRow(array $spec, array $rows, string $defaultLabel): TableRow
    {
        $cells = [];
        foreach ($this->columns as $index => $column) {
            $total = $spec[$column['key']] ?? null;
            if ($total === null) {
                $text = $index === 0 ? $defaultLabel : '';
            } else {
                $text = $this->aggregate($total, $column, $rows);
            }

            $cells[] = new TableCell($text, patch: new StylePatch(bold: true, align: $column['align']));
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
