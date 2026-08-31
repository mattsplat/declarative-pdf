<?php

declare(strict_types=1);

namespace Pdf\Builder;

/**
 * A column aggregate for {@see DataTable::totals()}.
 *
 *  - `sum()` / `avg()` reduce the column's RAW (pre-format) values. A value
 *    that is neither `int|float` nor a numeric string is skipped; `avg()` over
 *    no numeric values is `0`. The result is passed back through the column's
 *    own formatter, so a currency column totals in currency.
 *  - `count()` is the number of rows in the group (or the whole table).
 *  - `label(text)` is a fixed string — use it to title the row.
 *  - `of(fn)` calls `$fn(list<array<string, mixed>> $rows)` for the cell value.
 *
 * `count`, `label` and `of` values are used verbatim — the column formatter is
 * not applied to them.
 */
final readonly class Total
{
    public const KIND_SUM = 'sum';
    public const KIND_AVG = 'avg';
    public const KIND_COUNT = 'count';
    public const KIND_LABEL = 'label';
    public const KIND_CALLABLE = 'callable';

    private function __construct(
        public string $kind,
        public string $label = '',
        public ?\Closure $fn = null,
    ) {
    }

    public static function sum(): self
    {
        return new self(self::KIND_SUM);
    }

    public static function avg(): self
    {
        return new self(self::KIND_AVG);
    }

    public static function count(): self
    {
        return new self(self::KIND_COUNT);
    }

    public static function label(string $text): self
    {
        return new self(self::KIND_LABEL, $text);
    }

    /** @param callable(list<array<string, mixed>>): (int|float|string) $fn */
    public static function of(callable $fn): self
    {
        return new self(self::KIND_CALLABLE, fn: $fn(...));
    }
}
