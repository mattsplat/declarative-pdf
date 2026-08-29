<?php

declare(strict_types=1);

namespace Pdf\Render;

use Pdf\Font\FontRegistry;
use Pdf\Geometry\PageGeometry;
use Pdf\Interactive\AppearanceStream;
use Pdf\Interactive\FieldSpec;
use Pdf\Interactive\FieldType;
use Pdf\Layout\WidgetRect;

/**
 * Emits the interactive-form objects: one dictionary per `/AcroForm /Fields`
 * entry, one `/Subtype /Widget` annotation per widget, a self-drawn `/AP /N`
 * appearance Form XObject per widget state, and the catalog's `/AcroForm`
 * dictionary itself.
 *
 * Two phases so it can slot into {@see DocumentRenderer}'s existing ordering:
 * {@see plan()} allocates every object number up front (before the page loop
 * writes each page's `/Annots`), {@see write()} emits the bodies afterwards.
 *
 * Widget `/Rect` arrays are in default PDF user space; the top-left → bottom-left
 * conversion goes through the same {@see PageGeometry::flipY()} the link
 * annotations use.
 */
final class AcroFormWriter
{
    /** @var list<PlannedField> */
    private array $fields = [];

    /** @var array<int, list<int>> page index => widget annotation object numbers */
    private array $pageAnnots = [];

    private bool $hasSignature = false;

    public function __construct(private readonly PdfWriter $writer)
    {
    }

    /**
     * @param list<array{geometry: PageGeometry, pageObject: int, widgets: list<WidgetRect>}> $pages
     */
    public function plan(array $pages): void
    {
        /** @var list<string> $order */
        $order = [];
        /** @var array<string, array{spec: FieldSpec, items: list<array{pageIndex: int, pageObject: int, geometry: PageGeometry, widget: WidgetRect}>}> $grouped */
        $grouped = [];

        foreach ($pages as $pageIndex => $page) {
            foreach ($page['widgets'] as $widget) {
                $name = $widget->spec->fullName;
                if (!isset($grouped[$name])) {
                    $grouped[$name] = ['spec' => $widget->spec, 'items' => []];
                    $order[] = $name;
                }
                $grouped[$name]['items'][] = [
                    'pageIndex' => $pageIndex,
                    'pageObject' => $page['pageObject'],
                    'geometry' => $page['geometry'],
                    'widget' => $widget,
                ];
            }
        }

        $registry = $this->writer->registry();
        foreach ($order as $name) {
            $spec = $grouped[$name]['spec'];
            $items = $grouped[$name]['items'];
            $merged = count($items) === 1 && $spec->type !== FieldType::Radio;

            $fieldObject = $registry->allocate();
            if ($spec->type === FieldType::Signature) {
                $this->hasSignature = true;
            }

            /** @var list<PlannedWidget> $widgets */
            $widgets = [];
            foreach ($items as $item) {
                $widgetObject = $merged ? $fieldObject : $registry->allocate();
                $appearances = [];
                foreach ($this->appearanceStates($spec, $item['widget']) as $state) {
                    $appearances[$state] = $registry->allocate();
                }
                $widgets[] = new PlannedWidget(
                    $widgetObject,
                    $item['pageIndex'],
                    $item['pageObject'],
                    $item['geometry'],
                    $item['widget'],
                    $appearances,
                );
                $this->pageAnnots[$item['pageIndex']][] = $widgetObject;
            }

            $this->fields[] = new PlannedField($fieldObject, $spec, $merged, $widgets);
        }
    }

    public function isEmpty(): bool
    {
        return $this->fields === [];
    }

    /** @return list<int> */
    public function annotRefsForPage(int $pageIndex): array
    {
        return $this->pageAnnots[$pageIndex] ?? [];
    }

    /** Emit every form object and the `/AcroForm` dictionary; returns its object number. */
    public function write(FontRegistry $fonts): ?int
    {
        if ($this->fields === []) {
            return null;
        }

        foreach ($this->fields as $field) {
            foreach ($field->widgets as $widget) {
                $this->writeAppearances($field->spec, $widget);
            }
            $this->writeField($field);
        }

        return $this->writeAcroForm($fonts);
    }

    /** @return list<string> */
    private function appearanceStates(FieldSpec $spec, WidgetRect $widget): array
    {
        return match ($spec->type) {
            FieldType::Checkbox => ['Off', $spec->onState],
            FieldType::Radio => ['Off', (string) $widget->optionExport],
            default => ['N'],
        };
    }

    private function writeAppearances(FieldSpec $spec, PlannedWidget $widget): void
    {
        $w = $widget->rect->widthPt;
        $h = $widget->rect->heightPt;
        $fontObject = $spec->appearance->font->objectNumber ?? 0;
        $resources = sprintf(
            '/Resources <</ProcSet [/PDF /Text] /Font <</F%d %d 0 R>>>> ',
            $spec->appearance->font->index,
            $fontObject,
        );
        $extra = sprintf('/Type /XObject /Subtype /Form /FormType 1 /BBox [0 0 %s %s] %s', self::num($w), self::num($h), $resources);

        foreach ($widget->appearances as $state => $object) {
            $content = $this->appearanceContent($spec, $widget->rect, $state, $w, $h);
            $this->writer->streamObjectAt($object, $content, $extra);
        }
    }

    private function appearanceContent(FieldSpec $spec, WidgetRect $widget, string $state, float $w, float $h): string
    {
        return match ($spec->type) {
            FieldType::Text => AppearanceStream::textField($w, $h, $spec, $spec->value ?? ''),
            FieldType::Dropdown, FieldType::ListBox => AppearanceStream::choice($w, $h, $spec, $this->choiceDisplay($spec)),
            FieldType::Checkbox => AppearanceStream::toggle($w, $h, $spec, $state !== 'Off', 'check'),
            FieldType::Radio => AppearanceStream::toggle($w, $h, $spec, $state !== 'Off', 'dot'),
            FieldType::PushButton => AppearanceStream::pushButton($w, $h, $spec),
            FieldType::Signature => AppearanceStream::signature($w, $h, $spec),
        };
    }

    private function choiceDisplay(FieldSpec $spec): string
    {
        $value = $spec->value ?? '';
        foreach ($spec->options as $option) {
            if ($option['export'] === $value) {
                return $option['label'];
            }
        }

        return $value;
    }

    private function writeField(PlannedField $field): void
    {
        $spec = $field->spec;

        if ($field->merged) {
            $this->writer->beginObject($field->objectNumber);
            $this->writer->line('<</Type /Annot /Subtype /Widget');
            $this->writeFieldEntries($spec);
            $this->writeWidgetEntries($spec, $field->widgets[0], parentIsSelf: true);
            $this->writer->line('>>');
            $this->writer->endObject();

            return;
        }

        $this->writer->beginObject($field->objectNumber);
        $this->writer->line('<<');
        $this->writeFieldEntries($spec);
        $kids = implode(' ', array_map(
            static fn (PlannedWidget $w): string => $w->objectNumber . ' 0 R',
            $field->widgets,
        ));
        $this->writer->line('/Kids [' . $kids . ']');
        $this->writer->line('>>');
        $this->writer->endObject();

        foreach ($field->widgets as $widget) {
            $this->writer->beginObject($widget->objectNumber);
            $this->writer->line('<</Type /Annot /Subtype /Widget');
            $this->writer->line('/Parent ' . $field->objectNumber . ' 0 R');
            $this->writeWidgetEntries($spec, $widget, parentIsSelf: false);
            $this->writer->line('>>');
            $this->writer->endObject();
        }
    }

    /** The `/FT`, `/T`, `/Ff`, `/V`, `/TU`, `/DA`, `/Opt`, `/MaxLen` shared by a field and its parent. */
    private function writeFieldEntries(FieldSpec $spec): void
    {
        $this->writer->line('/FT /' . $spec->type->acroName());
        $this->writer->line('/T ' . PdfString::text($spec->fullName));
        if ($spec->flags !== 0) {
            $this->writer->line('/Ff ' . $spec->flags);
        }
        if ($spec->tooltip !== null && $spec->tooltip !== '') {
            $this->writer->line('/TU ' . PdfString::text($spec->tooltip));
        }
        if ($spec->maxLength !== null && $spec->maxLength > 0) {
            $this->writer->line('/MaxLen ' . $spec->maxLength);
        }
        if ($spec->options !== [] && ($spec->type === FieldType::Dropdown || $spec->type === FieldType::ListBox)) {
            $opt = '';
            foreach ($spec->options as $option) {
                $opt .= '[' . PdfString::text($option['export']) . ' ' . PdfString::text($option['label']) . ']';
            }
            $this->writer->line('/Opt [' . $opt . ']');
        }

        $this->writeValue($spec);

        if ($spec->type === FieldType::Text || $spec->type === FieldType::Dropdown || $spec->type === FieldType::ListBox) {
            $this->writer->line('/DA ' . PdfString::text($spec->appearance->defaultAppearance()));
            if ($spec->appearance->quadding !== 0) {
                $this->writer->line('/Q ' . $spec->appearance->quadding);
            }
        }
    }

    private function writeValue(FieldSpec $spec): void
    {
        $value = $spec->value;
        if ($value === null) {
            return;
        }

        if ($spec->type === FieldType::Checkbox || $spec->type === FieldType::Radio) {
            $this->writer->line('/V /' . self::name($value));
            $this->writer->line('/DV /' . self::name($value));

            return;
        }

        $this->writer->line('/V ' . PdfString::text($value));
    }

    private function writeWidgetEntries(FieldSpec $spec, PlannedWidget $widget, bool $parentIsSelf): void
    {
        $rect = $widget->rect;
        $geometry = $widget->geometry;
        $x1 = $rect->xPt;
        $x2 = $rect->xPt + $rect->widthPt;
        $yTop = $geometry->flipY($rect->yTopPt);
        $yBottom = $geometry->flipY($rect->yTopPt + $rect->heightPt);

        $this->writer->line(sprintf('/Rect [%.2F %.2F %.2F %.2F]', $x1, $yBottom, $x2, $yTop));
        $this->writer->line('/P ' . $widget->pageObject . ' 0 R');
        $this->writer->line('/F 4');

        $this->writeAppearanceCharacteristics($spec);

        if ($spec->type === FieldType::PushButton) {
            $this->writeButtonAction($spec);
        }

        $this->writeAppearanceDict($spec, $widget, $parentIsSelf);
    }

    private function writeAppearanceCharacteristics(FieldSpec $spec): void
    {
        $mk = '';
        if ($spec->appearance->backgroundColor !== null) {
            $mk .= '/BG [' . self::colorArray($spec->appearance->backgroundColor) . '] ';
        }
        if ($spec->appearance->borderColor !== null) {
            $mk .= '/BC [' . self::colorArray($spec->appearance->borderColor) . '] ';
        }
        if ($spec->type === FieldType::PushButton && $spec->buttonLabel !== null) {
            $mk .= '/CA ' . PdfString::text($spec->buttonLabel) . ' ';
        }
        if ($mk !== '') {
            $this->writer->line('/MK <<' . rtrim($mk) . '>>');
        }
        if ($spec->appearance->borderWidthPt > 0.0) {
            $this->writer->line(sprintf('/BS <</W %s /S /S>>', self::num($spec->appearance->borderWidthPt)));
        }
    }

    private function writeButtonAction(FieldSpec $spec): void
    {
        match ($spec->buttonKind) {
            \Pdf\Interactive\ButtonKind::Reset => $this->writer->line('/A <</S /ResetForm>>'),
            \Pdf\Interactive\ButtonKind::Submit => $this->writer->line(sprintf(
                '/A <</S /SubmitForm /F <</FS /URL /F %s>> /Flags %d>>',
                PdfString::text((string) $spec->submitUrl),
                $spec->submitFormat->actionFlags(),
            )),
            \Pdf\Interactive\ButtonKind::Push => null,
        };
    }

    private function writeAppearanceDict(FieldSpec $spec, PlannedWidget $widget, bool $parentIsSelf): void
    {
        if ($spec->type === FieldType::Checkbox || $spec->type === FieldType::Radio) {
            $on = $spec->type === FieldType::Radio ? (string) $widget->rect->optionExport : $spec->onState;
            $offObject = $widget->appearances['Off'] ?? 0;
            $onObject = $widget->appearances[$on] ?? 0;
            $this->writer->line(sprintf(
                '/AP <</N <</Off %d 0 R /%s %d 0 R>>>>',
                $offObject,
                self::name($on),
                $onObject,
            ));

            $current = $spec->value ?? 'Off';
            if ($spec->type === FieldType::Radio) {
                $current = $widget->rect->optionExport === $spec->value && $spec->value !== null ? $on : 'Off';
            } elseif ($current !== 'Off') {
                $current = $on;
            }
            $this->writer->line('/AS /' . self::name($current));

            return;
        }

        $object = $widget->appearances['N'] ?? 0;
        $this->writer->line('/AP <</N ' . $object . ' 0 R>>');
    }

    private function writeAcroForm(FontRegistry $fonts): int
    {
        $used = $fonts->used();
        $fieldRefs = implode(' ', array_map(
            static fn (PlannedField $f): string => $f->objectNumber . ' 0 R',
            $this->fields,
        ));

        $dr = '';
        foreach ($used as $font) {
            $dr .= sprintf('/F%d %d 0 R ', $font->index, $font->objectNumber ?? 0);
        }
        // The document-level /DA is only a fallback; point it at a real field font.
        $da = sprintf('/F%d 0 Tf 0 g', $this->fields[0]->spec->appearance->font->index);

        $calcOrder = $this->calculationOrder();

        $object = $this->writer->beginObject();
        $this->writer->line('<<');
        $this->writer->line('/Fields [' . $fieldRefs . ']');
        $this->writer->line('/DR <</Font <<' . rtrim($dr) . '>>>>');
        $this->writer->line('/DA ' . PdfString::text($da));
        if ($this->hasSignature) {
            $this->writer->line('/SigFlags 3');
        }
        if ($calcOrder !== []) {
            $this->writer->line('/CO [' . implode(' ', $calcOrder) . ']');
        }
        $this->writer->line('>>');
        $this->writer->endObject();

        return $object;
    }

    /** @return list<string> object references, in calculation order (JS layer populates this). */
    private function calculationOrder(): array
    {
        return [];
    }

    private static function colorArray(\Pdf\Color\Color $color): string
    {
        return sprintf('%.3F %.3F %.3F', $color->r / 255, $color->g / 255, $color->b / 255);
    }

    /** A bare name token: PDF names escape non-regular bytes as `#xx`. */
    private static function name(string $value): string
    {
        return preg_replace_callback(
            '/[^A-Za-z0-9_.\-]/',
            static fn (array $m): string => sprintf('#%02X', ord($m[0])),
            $value,
        ) ?? $value;
    }

    private static function num(float $value): string
    {
        $s = rtrim(rtrim(sprintf('%.2F', $value), '0'), '.');

        return $s === '' || $s === '-0' ? '0' : $s;
    }
}
