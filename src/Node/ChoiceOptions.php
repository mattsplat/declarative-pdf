<?php

declare(strict_types=1);

namespace Pdf\Node;

/**
 * Shared option normalisation for {@see Dropdown} and {@see ListBox}: a string
 * key becomes the export value, an integer key means "use the label for both".
 */
final class ChoiceOptions
{
    private function __construct()
    {
    }

    /**
     * @param iterable<int|string, string> $options
     * @return list<array{export: string, label: string}>
     */
    public static function normalise(iterable $options): array
    {
        $out = [];
        foreach ($options as $key => $label) {
            $out[] = is_string($key)
                ? ['export' => $key, 'label' => $label]
                : ['export' => $label, 'label' => $label];
        }

        return $out;
    }
}
