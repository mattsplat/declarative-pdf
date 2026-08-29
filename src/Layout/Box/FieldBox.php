<?php

declare(strict_types=1);

namespace Pdf\Layout\Box;

use Pdf\Color\Color;
use Pdf\Font\ResolvedFont;
use Pdf\Interactive\FieldSpec;
use Pdf\Interactive\FieldType;
use Pdf\Layout\Canvas;
use Pdf\Layout\WidgetRect;

/**
 * The measured box for one {@see \Pdf\Node\FormField}. Never splits — a field
 * that does not fit the remaining space moves whole to the next page, like an
 * image or a path.
 *
 * Rendering draws the optional label with ordinary text operators and records
 * the widget rectangle(s) through {@see Canvas::widget()}; the border, fill and
 * value are painted by the field's self-drawn `/AP` appearance stream, not here.
 */
final class FieldBox extends AbstractBox
{
    /**
     * @param list<array{export: string, label: string}> $rows radio options (encoded labels); empty for other types
     */
    public function __construct(
        private readonly FieldSpec $spec,
        private readonly float $widgetWidthPt,
        private readonly float $widgetHeightPt,
        private readonly ?string $labelText,
        private readonly ResolvedFont $labelFont,
        private readonly float $labelSizePt,
        private readonly Color $labelColor,
        private readonly float $labelLineHeightPt,
        private readonly float $marginBeforePt,
        private readonly float $marginAfterPt,
        private readonly array $rows = [],
        private readonly float $rowHeightPt = 16.0,
        private readonly float $rowGapPt = 4.0,
    ) {
    }

    public function marginBeforePt(): float
    {
        return $this->marginBeforePt;
    }

    public function marginAfterPt(): float
    {
        return $this->marginAfterPt;
    }

    public function minIntrinsicWidthPt(): float
    {
        return $this->widgetWidthPt;
    }

    public function maxIntrinsicWidthPt(): float
    {
        return $this->widgetWidthPt;
    }

    private function labelBlockHeightPt(): float
    {
        return $this->labelText !== null && $this->spec->type !== FieldType::Checkbox
            ? $this->labelLineHeightPt
            : 0.0;
    }

    private function controlBlockHeightPt(): float
    {
        if ($this->spec->type === FieldType::Radio) {
            $n = max(1, count($this->rows));

            return $n * $this->rowHeightPt + ($n - 1) * $this->rowGapPt;
        }

        if ($this->spec->type === FieldType::Checkbox) {
            return max($this->widgetHeightPt, $this->labelLineHeightPt);
        }

        return $this->widgetHeightPt;
    }

    public function contentHeightPt(): float
    {
        return $this->labelBlockHeightPt() + $this->controlBlockHeightPt();
    }

    public function split(float $availableHeightPt): array
    {
        return $this->contentHeightPt() <= $availableHeightPt + 1e-4 ? [$this, null] : [null, $this];
    }

    public function render(Canvas $canvas, float $xPt, float $yTopPt, float $widthPt): void
    {
        $y = $yTopPt;
        if ($this->labelBlockHeightPt() > 0.0 && $this->labelText !== null) {
            $canvas->text(
                $this->labelText,
                $xPt,
                $y + $this->labelSizePt,
                $this->labelFont->index,
                $this->labelSizePt,
                $this->labelColor,
            );
            $y += $this->labelBlockHeightPt();
        }

        match ($this->spec->type) {
            FieldType::Radio => $this->renderRadio($canvas, $xPt, $y),
            FieldType::Checkbox => $this->renderCheckbox($canvas, $xPt, $y),
            default => $canvas->widget(new WidgetRect(
                $xPt,
                $y,
                $this->widgetWidthPt,
                $this->widgetHeightPt,
                $this->spec,
            )),
        };
    }

    private function renderCheckbox(Canvas $canvas, float $xPt, float $yTopPt): void
    {
        $box = $this->widgetHeightPt;
        $rowH = $this->controlBlockHeightPt();
        $boxTop = $yTopPt + ($rowH - $box) / 2.0;
        $canvas->widget(new WidgetRect($xPt, $boxTop, $box, $box, $this->spec));

        if ($this->labelText !== null) {
            $canvas->text(
                $this->labelText,
                $xPt + $box + 6.0,
                $yTopPt + ($rowH + $this->labelSizePt) / 2.0 - 1.0,
                $this->labelFont->index,
                $this->labelSizePt,
                $this->labelColor,
            );
        }
    }

    private function renderRadio(Canvas $canvas, float $xPt, float $yTopPt): void
    {
        $selected = $this->spec->value ?? '';
        $y = $yTopPt;
        foreach ($this->rows as $row) {
            $canvas->widget(new WidgetRect(
                $xPt,
                $y,
                $this->rowHeightPt,
                $this->rowHeightPt,
                $this->spec,
                $row['export'],
                $row['export'] === $selected && $selected !== '',
            ));
            $canvas->text(
                $row['label'],
                $xPt + $this->rowHeightPt + 6.0,
                $y + ($this->rowHeightPt + $this->labelSizePt) / 2.0 - 1.0,
                $this->labelFont->index,
                $this->labelSizePt,
                $this->labelColor,
            );
            $y += $this->rowHeightPt + $this->rowGapPt;
        }
    }
}
