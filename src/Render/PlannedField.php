<?php

declare(strict_types=1);

namespace Pdf\Render;

use Pdf\Interactive\FieldSpec;

/**
 * One `/AcroForm /Fields` entry: its object number, its resolved
 * {@see FieldSpec}, and its widget(s).
 *
 * When `merged` is true the single widget's annotation dictionary *is* the
 * field dictionary (one object, listed in both `/Fields` and the page's
 * `/Annots`). Otherwise the field is a parent with `/Kids` and each widget is
 * its own object.
 */
final readonly class PlannedField
{
    /**
     * @param list<PlannedWidget> $widgets
     */
    public function __construct(
        public int $objectNumber,
        public FieldSpec $spec,
        public bool $merged,
        public array $widgets,
    ) {
    }
}
