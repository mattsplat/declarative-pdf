<?php

declare(strict_types=1);

namespace Pdf\Layout;

use Pdf\Exception\LayoutException;
use Pdf\Font\FontRegistry;
use Pdf\Image\ImageFactory;
use Pdf\Layout\Box\AnchorBox;
use Pdf\Layout\Box\ChartBox;
use Pdf\Layout\Box\ClipBox;
use Pdf\Layout\Box\ColumnsBox;
use Pdf\Layout\Box\ContainerBox;
use Pdf\Layout\Box\ImageBox;
use Pdf\Layout\Box\FieldBox;
use Pdf\Layout\Box\ListItemBox;
use Pdf\Layout\Box\PageBreakBox;
use Pdf\Layout\Box\PathBox;
use Pdf\Layout\Box\RuleBox;
use Pdf\Layout\Box\SpacerBox;
use Pdf\Layout\Box\StackBox;
use Pdf\Layout\Box\TableBox;
use Pdf\Layout\Box\TableCellBox;
use Pdf\Layout\Box\TableRowBox;
use Pdf\Layout\Box\TextBox;
use Pdf\Node\Anchor;
use Pdf\Node\BlockNode;
use Pdf\Node\BulletList;
use Pdf\Node\Chart;
use Pdf\Node\Clip;
use Pdf\Node\Columns;
use Pdf\Node\Checkbox;
use Pdf\Node\Component;
use Pdf\Node\Container;
use Pdf\Node\Dropdown;
use Pdf\Node\FormField;
use Pdf\Node\Heading;
use Pdf\Node\ImageBlock;
use Pdf\Node\ListBox;
use Pdf\Node\ListItem;
use Pdf\Node\OrderedList;
use Pdf\Node\PageBreak;
use Pdf\Node\Paragraph;
use Pdf\Node\Path;
use Pdf\Node\PushButton;
use Pdf\Node\RadioGroup;
use Pdf\Node\Rule;
use Pdf\Node\SignatureField;
use Pdf\Node\Spacer;
use Pdf\Node\Table;
use Pdf\Node\TextField;
use Pdf\Font\ResolvedFont;
use Pdf\Interactive\FieldActions;
use Pdf\Interactive\FieldAppearance;
use Pdf\Interactive\FieldFlag;
use Pdf\Interactive\FieldSpec;
use Pdf\Interactive\FieldType;
use Pdf\Interactive\Js;
use Pdf\Color\Color;
use Pdf\Style\TextAlign;
use Pdf\Render\ImageRegistry;
use Pdf\Render\ImportRegistry;
use Pdf\Style\Border;
use Pdf\Style\Style;
use Pdf\Style\StyleResolver;
use Pdf\Style\StylePatch;
use Pdf\Font\FontStyle;
use Pdf\Text\Encoding;
use Pdf\Text\InlineSequence;

/**
 * Turns the node tree into a tree of measured, splittable {@see Box}es.
 *
 * The Phase 2 successor to "measure a MultiCell before drawing it": every block
 * type is broken into lines / child boxes and its height computed.
 */
final class Measurer
{
    /** Guards against a {@see Component} whose `body()` reaches itself. */
    /**
     * The {@see Component}s currently being expanded, keyed by object id — a
     * transient guard against a `body()` that reaches itself. Never emitted.
     *
     * @var array<int, true>
     */
    private array $componentPath = [];

    public function __construct(
        private readonly StyleResolver $styles,
        private readonly FontRegistry $fonts,
        private readonly LineBreaker $breaker = new LineBreaker(),
        private readonly ImageFactory $images = new ImageFactory(),
        private readonly ImageRegistry $imageRegistry = new ImageRegistry(),
        private readonly ImportRegistry $importRegistry = new ImportRegistry(),
    ) {
    }

    /**
     * A clone that resolves styles through a font-scaled {@see StyleResolver},
     * sharing this measurer's font, image and import registries so anything it
     * measures still lands in the same resource tables.
     */
    public function withFontScale(float $scale): self
    {
        return new self(
            $this->styles->withFontScale($scale),
            $this->fonts,
            $this->breaker,
            $this->images,
            $this->imageRegistry,
            $this->importRegistry,
        );
    }

    public function fonts(): FontRegistry
    {
        return $this->fonts;
    }

    public function imageRegistry(): ImageRegistry
    {
        return $this->imageRegistry;
    }

    public function images(): ImageFactory
    {
        return $this->images;
    }

    public function importRegistry(): ImportRegistry
    {
        return $this->importRegistry;
    }

    /**
     * @param iterable<BlockNode> $nodes
     */
    public function measureStack(iterable $nodes, float $widthPt, Style $parentStyle): StackBox
    {
        $boxes = [];
        foreach ($nodes as $node) {
            $boxes[] = $this->measureBlock($node, $widthPt, $parentStyle);
        }

        return new StackBox($boxes);
    }

    public function measureBlock(BlockNode $node, float $widthPt, Style $parentStyle): Box
    {
        return match (true) {
            $node instanceof Heading, $node instanceof Paragraph => $this->measureText($node, $widthPt, $parentStyle),
            $node instanceof Spacer => new SpacerBox($node->heightPt),
            $node instanceof PageBreak => new PageBreakBox(),
            $node instanceof Rule => $this->measureRule($node, $parentStyle),
            $node instanceof Path => $this->measurePath($node, $parentStyle),
            $node instanceof Chart => $this->measureChart($node, $parentStyle),
            $node instanceof Clip => $this->measureClip($node, $parentStyle),
            $node instanceof Container => $this->measureContainer($node, $widthPt, $parentStyle),
            $node instanceof BulletList, $node instanceof OrderedList => $this->measureList($node, $widthPt, $parentStyle),
            $node instanceof ImageBlock => $this->measureImage($node, $widthPt, $parentStyle),
            $node instanceof Columns => $this->measureColumns($node, $widthPt, $parentStyle),
            $node instanceof Table => $this->measureTable($node, $widthPt, $parentStyle),
            $node instanceof Anchor => new AnchorBox($node->name),
            $node instanceof FormField => $this->measureFormField($node, $widthPt, $parentStyle),
            $node instanceof Component => $this->measureComponent($node, $widthPt, $parentStyle),
            default => throw new LayoutException('Unsupported block node: ' . $node::class),
        };
    }

    private function measureComponent(Component $node, float $widthPt, Style $parentStyle): Box
    {
        $id = spl_object_id($node);
        if (isset($this->componentPath[$id])) {
            throw new LayoutException(sprintf('Component %s expands to itself.', $node::class));
        }

        $this->componentPath[$id] = true;
        try {
            $body = $node->body();
            $children = $body instanceof BlockNode ? [$body] : $body;
            $patch = $node->patch();

            // A non-empty patch wraps the body like an implicit Container, so
            // padding / border / background / inheritance all reuse that path.
            return $patch->isEmpty()
                ? $this->measureStack($children, $widthPt, $parentStyle)
                : $this->measureContainer(new Container($children, $patch), $widthPt, $parentStyle);
        } finally {
            unset($this->componentPath[$id]);
        }
    }

    private function measureFormField(FormField $node, float $widthPt, Style $parentStyle): FieldBox
    {
        $style = $this->styles->resolveBlock($node, $parentStyle);
        $font = $this->fonts->use($style->fontFamily, $style->fontFace);
        $encoding = $font->definition->encoding;
        $available = max(1.0, $widthPt);

        $spec = $this->buildFieldSpec($node, $style, $font);
        [$widgetW, $widgetH, $rows, $rowH, $rowGap] = $this->fieldGeometry($node, $style, $font, $available);

        $label = $node->fieldLabel();
        $labelEncoded = $label !== null && $label !== ''
            ? Encoding::forFont($label, $encoding)
            : null;

        return new FieldBox(
            $spec,
            $widgetW,
            $widgetH,
            $labelEncoded,
            $font,
            $style->fontSizePt,
            $style->color,
            $style->fontSizePt * 1.3,
            $style->spaceBeforePt > 0.0 ? $style->spaceBeforePt : 6.0,
            $style->spaceAfterPt > 0.0 ? $style->spaceAfterPt : 6.0,
            $rows,
            $rowH,
            $rowGap,
        );
    }

    private function buildFieldSpec(FormField $node, Style $style, ResolvedFont $font): FieldSpec
    {
        $border = Color::gray(128);
        $subtleFill = Color::gray(247);
        $common = ($node->isRequired() ? FieldFlag::REQUIRED : 0)
            | ($node->isReadOnly() ? FieldFlag::READ_ONLY : 0);

        $appearance = static fn (int $quad, ?Color $b, ?Color $bg, float $bw): FieldAppearance
            => new FieldAppearance($font, 0.0, $style->color, $b, $bg, $bw, $quad);

        return match (true) {
            $node instanceof TextField => new FieldSpec(
                FieldType::Text,
                $node->name,
                $common
                    | ($node->multiline ? FieldFlag::MULTILINE : 0)
                    | ($node->password ? FieldFlag::PASSWORD : 0)
                    | ($node->comb && $node->maxLength !== null ? FieldFlag::COMB | FieldFlag::DO_NOT_SCROLL : 0),
                new FieldAppearance($font, $node->fontSizePt, $style->color, $border, $subtleFill, 0.75, $this->quadding($node->align)),
                $node->tooltip,
                $node->value !== '' ? $node->value : null,
                null,
                $node->maxLength,
                actions: $this->textFieldActions($node),
            ),
            $node instanceof Checkbox => new FieldSpec(
                FieldType::Checkbox,
                $node->name,
                $common,
                $appearance(0, $border, Color::white(), 0.75),
                $node->tooltip,
                $node->checked ? $node->exportValue : 'Off',
                null,
                null,
                [],
                $node->exportValue,
            ),
            $node instanceof RadioGroup => new FieldSpec(
                FieldType::Radio,
                $node->name,
                $common | FieldFlag::RADIO,
                $appearance(0, $border, Color::white(), 0.75),
                $node->tooltip,
                $node->value !== '' ? $node->value : null,
                null,
                null,
                array_map(static fn ($o): array => ['export' => $o->export, 'label' => $o->label], $node->options),
            ),
            $node instanceof Dropdown => new FieldSpec(
                FieldType::Dropdown,
                $node->name,
                $common | FieldFlag::COMBO
                    | ($node->editable ? FieldFlag::EDIT : 0)
                    | ($node->sort ? FieldFlag::SORT : 0),
                $appearance(0, $border, $subtleFill, 0.75),
                $node->tooltip,
                $node->value !== '' ? $node->value : null,
                null,
                null,
                $node->options,
            ),
            $node instanceof ListBox => new FieldSpec(
                FieldType::ListBox,
                $node->name,
                $common
                    | ($node->multiSelect ? FieldFlag::MULTI_SELECT : 0)
                    | ($node->sort ? FieldFlag::SORT : 0),
                $appearance(0, $border, $subtleFill, 0.75),
                $node->tooltip,
                $node->selected[0] ?? null,
                null,
                null,
                $node->options,
            ),
            $node instanceof PushButton => new FieldSpec(
                FieldType::PushButton,
                $node->name,
                $common | FieldFlag::PUSHBUTTON,
                $appearance(1, Color::gray(110), Color::gray(224), 1.0),
                $node->tooltip,
                null,
                null,
                null,
                [],
                'Yes',
                $node->label,
                $node->kind,
                $node->submitUrl,
                $node->submitFormat,
                new FieldActions(click: $node->script),
            ),
            $node instanceof SignatureField => new FieldSpec(
                FieldType::Signature,
                $node->name,
                $common,
                $appearance(0, Color::gray(140), Color::gray(242), 1.0),
                $node->tooltip,
            ),
            default => throw new LayoutException('Unsupported form field: ' . $node::class),
        };
    }

    private function textFieldActions(TextField $node): FieldActions
    {
        $keystroke = $node->keystroke;
        if ($keystroke === null && $node->format?->keystrokeSource !== null) {
            $keystroke = Js::raw($node->format->keystrokeSource);
        }

        return new FieldActions(
            keystroke: $keystroke,
            format: $node->format,
            validate: $node->validate,
            calculate: $node->calculate,
        );
    }

    /**
     * @return array{0: float, 1: float, 2: list<array{export: string, label: string}>, 3: float, 4: float}
     */
    private function fieldGeometry(FormField $node, Style $style, ResolvedFont $font, float $available): array
    {
        $line = $style->fontSizePt * 1.3;
        $box = static fn (?float $w): float => min($w ?? $available, $available);

        if ($node instanceof Checkbox) {
            $size = max(10.0, $node->sizePt ?? $style->fontSizePt);

            return [$size, $size, [], 16.0, 4.0];
        }

        return match (true) {
            $node instanceof RadioGroup => [
                $node->rowHeightPt,
                $node->rowHeightPt,
                array_map(
                    static fn ($o): array => [
                        'export' => $o->export,
                        'label' => Encoding::forFont($o->label, $font->definition->encoding),
                    ],
                    $node->options,
                ),
                $node->rowHeightPt,
                $node->rowGapPt,
            ],
            $node instanceof TextField => [
                $box($node->widthPt),
                $node->heightPt ?? ($node->multiline ? 3.0 * $line + 6.0 : max(14.0, $line + 4.0)),
                [],
                16.0,
                4.0,
            ],
            $node instanceof Dropdown => [$box($node->widthPt), $node->heightPt ?? ($line + 8.0), [], 16.0, 4.0],
            $node instanceof ListBox => [$box($node->widthPt), $node->heightPt ?? (4.0 * $line), [], 16.0, 4.0],
            $node instanceof PushButton => [
                min(
                    $node->widthPt ?? ($font->metrics->stringWidth(
                        Encoding::forFont($node->label, $font->definition->encoding),
                        $style->fontSizePt,
                    ) + 28.0),
                    $available,
                ),
                $node->heightPt ?? ($line + 10.0),
                [],
                16.0,
                4.0,
            ],
            $node instanceof SignatureField => [$box($node->widthPt ?? 200.0), $node->heightPt ?? 48.0, [], 16.0, 4.0],
            default => [$available, 20.0, [], 16.0, 4.0],
        };
    }

    private function quadding(TextAlign $align): int
    {
        return match ($align) {
            TextAlign::Center => 1,
            TextAlign::Right => 2,
            default => 0,
        };
    }

    private function measureText(Heading|Paragraph $node, float $widthPt, Style $parentStyle): TextBox
    {
        $style = $this->styles->resolveBlock($node, $parentStyle);
        $runs = $this->resolveRuns($node->content, $style);
        [$minIntrinsic, $maxIntrinsic] = IntrinsicText::measure($runs);
        $lines = $this->breakRuns($runs, $style, $widthPt);

        return new TextBox($style, $lines, true, true, $minIntrinsic, $maxIntrinsic);
    }

    /**
     * Min/max content width of any block, for table column autosizing.
     *
     * @return array{0: float, 1: float}
     */
    public function intrinsicBlock(BlockNode $node, Style $parentStyle): array
    {
        if ($node instanceof Heading || $node instanceof Paragraph) {
            $style = $this->styles->resolveBlock($node, $parentStyle);

            return IntrinsicText::measure($this->resolveRuns($node->content, $style));
        }

        $box = $this->measureBlock($node, 100_000.0, $parentStyle);

        return [$box->minIntrinsicWidthPt(), $box->maxIntrinsicWidthPt()];
    }

    private function measureRule(Rule $node, Style $parentStyle): RuleBox
    {
        $style = $this->styles->resolveBlock($node, $parentStyle);
        $marginBefore = $style->spaceBeforePt > 0.0 ? $style->spaceBeforePt : 6.0;
        $marginAfter = $style->spaceAfterPt > 0.0 ? $style->spaceAfterPt : 6.0;

        return new RuleBox($node->thicknessPt, $node->color ?? $style->color, $marginBefore, $marginAfter);
    }

    private function measurePath(Path $node, Style $parentStyle): PathBox
    {
        $style = $this->styles->resolveBlock($node, $parentStyle);

        return new PathBox(
            $node->commands,
            $node->widthPt,
            $node->heightPt,
            $node->paint,
            $style->spaceBeforePt,
            $style->spaceAfterPt,
        );
    }

    private function measureChart(Chart $node, Style $parentStyle): ChartBox
    {
        $style = $this->styles->resolveBlock($node, $parentStyle);
        $font = $this->fonts->use($style->fontFamily, $style->fontFace);

        return new ChartBox(
            $node->kind,
            $node->series,
            $node->categories,
            $node->widthPt,
            $node->heightPt,
            $node->legend,
            $node->axes,
            $font,
            // Axis and legend text is captioning: a notch below body size, floored
            // so it stays legible, and never larger than the resolved size.
            max(6.0, min($style->fontSizePt * 0.7, 9.0)),
            $style->color,
            $style->spaceBeforePt,
            $style->spaceAfterPt,
        );
    }

    private function measureClip(Clip $node, Style $parentStyle): ClipBox
    {
        $style = $this->styles->resolveBlock($node, $parentStyle);
        $inner = $this->measureStack($node->children, $node->path->widthPt, $style);

        return new ClipBox(
            $node->path->commands,
            $node->path->widthPt,
            $node->path->heightPt,
            $node->clipRule,
            $inner,
            $style->spaceBeforePt,
            $style->spaceAfterPt,
        );
    }

    private function measureContainer(Container $node, float $widthPt, Style $parentStyle): ContainerBox
    {
        $style = $this->styles->resolveBlock($node, $parentStyle);
        $innerWidth = $widthPt - $style->border->widthPt->horizontal() - $style->paddingPt->horizontal();
        $inner = $this->measureStack($node->children, max(0.0, $innerWidth), $style);

        return new ContainerBox(
            $inner,
            $style->paddingPt,
            $style->border,
            $style->background,
            $style,
        );
    }

    private function measureList(BulletList|OrderedList $node, float $widthPt, Style $parentStyle): ContainerBox
    {
        $style = $this->styles->resolveBlock($node, $parentStyle);
        $markerFont = $this->fonts->use($style->fontFamily, $style->fontFace);
        $gutter = $node->gutterPt;
        $itemSpacing = $node->itemSpacingPt;

        $count = count($node->items);
        $items = [];
        foreach ($node->items as $index => $item) {
            $inner = $this->measureStack($item->children, max(0.0, $widthPt - $gutter), $style);
            $marker = $node instanceof OrderedList
                ? ($node->start + $index) . $node->suffix
                : $node->marker;
            $marker = Encoding::forFont($marker, $markerFont->definition->encoding);

            $items[] = new ListItemBox(
                $inner,
                $gutter,
                $marker,
                $markerFont,
                $style->fontSizePt,
                $style->color,
                $inner->firstLineAscentPt() ?? $style->fontSizePt * 0.8,
                showMarker: true,
                spacingAfterPt: $index < $count - 1 ? $itemSpacing : 0.0,
            );
        }

        return new ContainerBox(
            new StackBox($items),
            new \Pdf\Geometry\Edges(),
            new Border(),
            null,
            $style,
        );
    }

    private function measureTable(Table $node, float $widthPt, Style $parentStyle): TableBox
    {
        $style = $this->styles->resolveBlock($node, $parentStyle);
        $gridColumns = count($node->columns);
        $available = min($node->totalWidthPt ?? $widthPt, $widthPt);
        $paddingH = $node->cellPaddingPt->horizontal();

        $headerBase = (new StylePatch(fontStyle: FontStyle::Bold))->applyTo($style);

        // --- per-column intrinsic widths (padding folded in) ---
        $colMin = array_fill(0, $gridColumns, 0.0);
        $colMax = array_fill(0, $gridColumns, 0.0);
        /** @var list<array{start:int, span:int, min:float, max:float}> $spanning */
        $spanning = [];

        foreach ($node->rows as $rowIndex => $row) {
            $isHeader = $rowIndex < $node->headerRows;
            $cellBase = $isHeader ? $headerBase : $style;
            $column = 0;
            foreach ($row->cells as $cell) {
                $cellStyle = $cell->patch->applyTo($cellBase);
                $cellMin = 0.0;
                $cellMax = 0.0;
                foreach ($cell->children as $child) {
                    [$m0, $m1] = $this->intrinsicBlock($child, $cellStyle);
                    $cellMin = max($cellMin, $m0);
                    $cellMax = max($cellMax, $m1);
                }
                $cellMin += $paddingH;
                $cellMax += $paddingH;

                if ($cell->colspan === 1) {
                    $colMin[$column] = max($colMin[$column], $cellMin);
                    $colMax[$column] = max($colMax[$column], $cellMax);
                } else {
                    $spanning[] = ['start' => $column, 'span' => $cell->colspan, 'min' => $cellMin, 'max' => $cellMax];
                }
                $column += $cell->colspan;
            }
        }

        foreach ($spanning as $span) {
            $currentMin = array_sum(array_slice($colMin, $span['start'], $span['span']));
            $currentMax = array_sum(array_slice($colMax, $span['start'], $span['span']));
            $addMin = ($span['min'] - $currentMin) / $span['span'];
            $addMax = ($span['max'] - $currentMax) / $span['span'];
            for ($k = $span['start']; $k < $span['start'] + $span['span']; $k++) {
                if ($addMin > 0.0) {
                    $colMin[$k] += $addMin;
                }
                if ($addMax > 0.0) {
                    $colMax[$k] += $addMax;
                }
            }
        }

        $content = [];
        for ($i = 0; $i < $gridColumns; $i++) {
            $content[] = ['min' => $colMin[$i], 'max' => max($colMax[$i], $colMin[$i])];
        }

        $columnWidths = TableLayout::resolve($available, $node->columns, $content);

        // --- lay out each cell at its resolved width ---
        /** @var list<TableRowBox> $rows */
        $rows = [];
        /** @var list<TableRowBox> $headerRows */
        $headerRows = [];

        foreach ($node->rows as $rowIndex => $row) {
            $isHeader = $rowIndex < $node->headerRows;
            $cellBase = $isHeader ? $headerBase : $style;
            $column = 0;
            /** @var list<TableCellBox> $cells */
            $cells = [];
            foreach ($row->cells as $cell) {
                $cellWidth = array_sum(array_slice($columnWidths, $column, $cell->colspan));
                $cellStyle = $cell->patch->applyTo($cellBase);
                $innerWidth = max(0.0, $cellWidth - $paddingH);
                $inner = $this->measureStack($cell->children, $innerWidth, $cellStyle);

                $cells[] = new TableCellBox(
                    $inner,
                    $column,
                    $cell->colspan,
                    $cellWidth,
                    $node->cellPaddingPt,
                    $cell->verticalAlign,
                    $cell->background ?? ($isHeader ? $node->headerBackground : null),
                );
                $column += $cell->colspan;
            }

            $rowBox = TableRowBox::fromCells($cells, $isHeader);
            $rows[] = $rowBox;
            if ($isHeader) {
                $headerRows[] = $rowBox;
            }
        }

        return new TableBox(
            $columnWidths,
            $rows,
            $headerRows,
            $node->borderWidthPt,
            $node->borderColor,
            $node->headerBackground,
            $style,
            $node->repeatHeader,
        );
    }

    private function measureColumns(Columns $node, float $widthPt, Style $parentStyle): ColumnsBox
    {
        $style = $this->styles->resolveBlock($node, $parentStyle);
        $columnWidth = ($widthPt - $node->gutterPt * ($node->count - 1)) / $node->count;
        $content = $this->measureStack($node->children, max(0.0, $columnWidth), $style);

        return ColumnsBox::layout($content, $node->count, $widthPt, $node->gutterPt, $style);
    }

    private function measureImage(ImageBlock $node, float $widthPt, Style $parentStyle): ImageBox
    {
        $style = $this->styles->resolveBlock($node, $parentStyle);
        $resource = $this->images->fromPath($node->path);
        $resolved = $this->imageRegistry->use($resource);

        $naturalWidth = $resource->widthPx * 72.0 / $node->dpi;
        $naturalHeight = $resource->heightPx * 72.0 / $node->dpi;

        if ($node->widthPt !== null && $node->heightPt !== null) {
            $w = $node->widthPt;
            $h = $node->heightPt;
        } elseif ($node->widthPt !== null) {
            $w = $node->widthPt;
            $h = $w / max($resource->aspectRatio(), 1e-6);
        } elseif ($node->heightPt !== null) {
            $h = $node->heightPt;
            $w = $h * $resource->aspectRatio();
        } else {
            $w = $naturalWidth;
            $h = $naturalHeight;
        }

        if ($w > $widthPt && $w > 0.0) {
            $scale = $widthPt / $w;
            $w = $widthPt;
            $h *= $scale;
        }

        return new ImageBox($resolved->index, $w, $h, $node->align, $style);
    }

    /** @return list<ResolvedRun> */
    private function resolveRuns(InlineSequence $sequence, Style $blockStyle): array
    {
        $runs = [];
        foreach ($sequence->runs as $run) {
            $runStyle = $this->styles->resolveInline($run->patch, $blockStyle);
            $font = $this->fonts->use($runStyle->fontFamily, $runStyle->fontFace);

            if ($run->isImage()) {
                $runs[] = $this->resolveInlineImage($run, $runStyle, $font);
                continue;
            }

            if ($run->text === '') {
                continue;
            }

            $runs[] = new ResolvedRun(
                text: Encoding::forFont($run->text, $font->definition->encoding),
                font: $font,
                fontSizePt: $runStyle->fontSizePt,
                color: $runStyle->color,
                link: $run->link,
                underline: $runStyle->underline,
                strikethrough: $runStyle->strikethrough,
                baselineShiftPt: $runStyle->baselineShift * $runStyle->fontSizePt,
            );
        }

        return $runs;
    }

    private function resolveInlineImage(
        \Pdf\Text\TextRun $run,
        Style $runStyle,
        ResolvedFont $font,
    ): ResolvedRun {
        $resource = $this->images->fromPath((string) $run->imagePath);
        $resolved = $this->imageRegistry->use($resource);

        $naturalWidth = $resource->widthPx * 72.0 / 96.0;
        $naturalHeight = $resource->heightPx * 72.0 / 96.0;

        if ($run->imageWidthPt !== null && $run->imageHeightPt !== null) {
            $w = $run->imageWidthPt;
            $h = $run->imageHeightPt;
        } elseif ($run->imageWidthPt !== null) {
            $w = $run->imageWidthPt;
            $h = $w / max($resource->aspectRatio(), 1e-6);
        } elseif ($run->imageHeightPt !== null) {
            $h = $run->imageHeightPt;
            $w = $h * $resource->aspectRatio();
        } else {
            $w = $naturalWidth;
            $h = $naturalHeight;
        }

        return new ResolvedRun(
            text: '',
            font: $font,
            fontSizePt: $runStyle->fontSizePt,
            color: $runStyle->color,
            link: $run->link,
            baselineShiftPt: $runStyle->baselineShift * $runStyle->fontSizePt,
            imageIndex: $resolved->index,
            imageWidthPt: $w,
            imageHeightPt: $h,
        );
    }

    /**
     * @param list<ResolvedRun> $runs
     * @return list<TextLine>
     */
    private function breakRuns(array $runs, Style $blockStyle, float $widthPt): array
    {
        if ($runs === []) {
            $this->fonts->use($blockStyle->fontFamily, $blockStyle->fontFace);
            $height = $blockStyle->lineHeightPt();

            return [new TextLine([], 0.0, $height, 0.5 * $height + 0.3 * $blockStyle->fontSizePt, 0, true)];
        }

        return $this->breaker->break($runs, $widthPt, $blockStyle->lineHeight);
    }
}
