<?php

declare(strict_types=1);

namespace Pdf\Interactive;

/**
 * A snippet of PDF (Acrobat) JavaScript for a field action or a document-level
 * script, with a handful of canned recipes for the common calculator and
 * formatting cases plus a {@see self::raw()} escape hatch.
 *
 * **Viewer support is the whole story.** Adobe Acrobat / Reader run PDF
 * JavaScript fully and Foxit runs most of it; Chrome (pdfium), macOS Preview
 * and Firefox's pdf.js run little or none, many organisations disable PDF
 * JavaScript by policy, and PDF/A forbids it outright. A form built with
 * self-drawn appearance streams stays fillable, printable and saveable
 * everywhere — treat every `Js` action as an Acrobat-only enhancement on top,
 * and design so a field that never recalculates is still a usable blank.
 *
 * Some recipes ({@see self::formatCurrency()}, {@see self::formatNumber()},
 * {@see self::formatPercent()}) also carry a matching keystroke filter in
 * {@see self::$keystrokeSource}; a text field given one of them as its `format`
 * action picks that up as its `/K` action automatically.
 */
final readonly class Js
{
    public function __construct(
        public string $source,
        public ?string $keystrokeSource = null,
    ) {
    }

    /** Verbatim JavaScript — `event.value`, `this.getField(...)`, `app.alert(...)`, … */
    public static function raw(string $source): self
    {
        return new self($source);
    }

    /** Sum the named fields into `event.value` (Acrobat `AFSimple_Calculate`). */
    public static function sum(string ...$fields): self
    {
        return self::simpleCalculate('SUM', $fields);
    }

    public static function product(string ...$fields): self
    {
        return self::simpleCalculate('PRD', $fields);
    }

    public static function average(string ...$fields): self
    {
        return self::simpleCalculate('AVG', $fields);
    }

    public static function minimum(string ...$fields): self
    {
        return self::simpleCalculate('MIN', $fields);
    }

    public static function maximum(string ...$fields): self
    {
        return self::simpleCalculate('MAX', $fields);
    }

    /**
     * Reject the entry unless it is a number within `[$min, $max]` (either bound
     * may be null). Shows `$message`, or a generated one, on failure.
     */
    public static function validateRange(?float $min, ?float $max, ?string $message = null): self
    {
        $checks = [];
        if ($min !== null) {
            $checks[] = 'v < ' . self::number($min);
        }
        if ($max !== null) {
            $checks[] = 'v > ' . self::number($max);
        }
        $condition = $checks === [] ? 'false' : implode(' || ', $checks);
        $text = $message ?? self::rangeMessage($min, $max);

        return new self(
            'if (event.value != "") { var v = parseFloat(event.value); '
            . 'if (isNaN(v) || ' . $condition . ') { event.rc = false; app.alert(' . self::string($text) . '); } }',
        );
    }

    /** Format as currency; the companion keystroke filter restricts typed input. */
    public static function formatCurrency(int $decimals = 2, string $symbol = '$', bool $symbolBefore = true): self
    {
        $args = sprintf(
            '%d, 0, 0, 0, %s, %s',
            $decimals,
            self::string($symbol),
            $symbolBefore ? 'true' : 'false',
        );

        return new self('AFNumber_Format(' . $args . ');', 'AFNumber_Keystroke(' . $args . ');');
    }

    /** Format as a plain number with grouped thousands. */
    public static function formatNumber(int $decimals = 2): self
    {
        $args = sprintf('%d, 0, 0, 0, "", true', $decimals);

        return new self('AFNumber_Format(' . $args . ');', 'AFNumber_Keystroke(' . $args . ');');
    }

    /** Format `0.25` as `25%`. */
    public static function formatPercent(int $decimals = 1): self
    {
        $args = sprintf('%d, 0', $decimals);

        return new self('AFPercent_Format(' . $args . ');', 'AFPercent_Keystroke(' . $args . ');');
    }

    /**
     * @param list<string> $fields
     */
    private static function simpleCalculate(string $operation, array $fields): self
    {
        $array = implode(', ', array_map(static fn (string $f): string => self::string($f), $fields));

        return new self(sprintf('AFSimple_Calculate("%s", new Array(%s));', $operation, $array));
    }

    private static function rangeMessage(?float $min, ?float $max): string
    {
        return match (true) {
            $min !== null && $max !== null => sprintf('Enter a value between %s and %s.', self::number($min), self::number($max)),
            $min !== null => sprintf('Enter a value of at least %s.', self::number($min)),
            $max !== null => sprintf('Enter a value of at most %s.', self::number($max)),
            default => 'Enter a valid number.',
        };
    }

    private static function number(float $value): string
    {
        $s = rtrim(rtrim(sprintf('%.4F', $value), '0'), '.');

        return $s === '' || $s === '-0' ? '0' : $s;
    }

    /** A JavaScript double-quoted string literal. */
    private static function string(string $value): string
    {
        return '"' . str_replace(['\\', '"', "\n", "\r"], ['\\\\', '\\"', '\\n', '\\r'], $value) . '"';
    }
}
