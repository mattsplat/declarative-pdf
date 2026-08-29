<?php

declare(strict_types=1);

namespace Pdf\Chart;

use Pdf\Color\Color;
use Pdf\Exception\PdfException;

/**
 * One named run of data values in a {@see \Pdf\Node\Chart} — a bar group, a
 * line, or (for a pie) the single set of slice magnitudes.
 *
 * `$color` is optional: a chart fills in any missing series colour from the
 * {@see Palette} by position, so the common case stays a label and some numbers.
 */
final readonly class Series
{
    /** @var list<float> */
    public array $values;

    /** @param iterable<float|int> $values */
    public function __construct(
        public string $label,
        iterable $values,
        public ?Color $color = null,
    ) {
        $floats = [];
        foreach ($values as $value) {
            $floats[] = (float) $value;
        }
        if ($floats === []) {
            throw new PdfException('A chart series needs at least one value.');
        }
        $this->values = $floats;
    }

    /** @param iterable<float|int> $values */
    public static function of(string $label, iterable $values, ?Color $color = null): self
    {
        return new self($label, $values, $color);
    }

    public function max(): float
    {
        return max($this->values);
    }

    public function min(): float
    {
        return min($this->values);
    }
}
