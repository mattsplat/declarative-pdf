<?php

declare(strict_types=1);

namespace Pdf\Builder;

use Pdf\Color\Color;
use Pdf\Geometry\BoxAlign;
use Pdf\Geometry\Edges;
use Pdf\Geometry\Fit;
use Pdf\Geometry\Orientation;
use Pdf\Geometry\PageSize;
use Pdf\Geometry\Rect;
use Pdf\Geometry\ShrinkMode;
use Pdf\Geometry\Unit;
use Pdf\Exception\LayoutException;
use Pdf\Layout\Grid;
use Pdf\Layout\Measurer;
use Pdf\Node\Anchor;
use Pdf\Node\BlockNode;
use Pdf\Node\BulletList;
use Pdf\Node\Chart;
use Pdf\Node\Clip;
use Pdf\Node\Columns;
use Pdf\Node\Component;
use Pdf\Node\Container;
use Pdf\Node\FormField;
use Pdf\Node\Heading;
use Pdf\Node\ImageBlock;
use Pdf\Node\ListItem;
use Pdf\Node\OrderedList;
use Pdf\Node\Page;
use Pdf\Node\PageBreak;
use Pdf\Node\PageMaster;
use Pdf\Layout\PageContext;
use Pdf\Node\Paragraph;
use Pdf\Node\Path;
use Pdf\Node\Transition;
use Pdf\Node\Watermark;
use Pdf\Node\Placement;
use Pdf\Node\Placement\Blocks;
use Pdf\Node\Placement\Frame;
use Pdf\Node\Placement\PdfPage;
use Pdf\Node\Placement\Picture;
use Pdf\Style\Border;
use Pdf\Node\Rule;
use Pdf\Node\Spacer;
use Pdf\Node\Table;
use Pdf\Node\TableRow;
use Pdf\Style\ColumnWidth;
use Pdf\Style\FillRule;
use Pdf\Style\Style;
use Pdf\Style\StylePatch;
use Pdf\Style\TextAlign;
use Pdf\Text\Html;
use Pdf\Text\InlineSequence;
use Pdf\Text\TextMeasurer;

final class PageBuilder
{
    private PageSize $size;
    private Orientation $orientation = Orientation::Portrait;
    private Edges $marginsPt;
    private ?\Closure $header = null;
    private ?\Closure $footer = null;
    private ?Watermark $watermark = null;
    private ?Transition $transition = null;
    private ?float $autoAdvanceSec = null;

    /** @var list<BlockNode> */
    private array $children = [];

    /** @var list<Placement> */
    private array $placements = [];

    private Unit $unit = Unit::Mm;

    private readonly Style $baseStyle;

    /**
     * `$measurer` is supplied by {@see DocumentBuilder} once the renderer is
     * known; it powers {@see self::textWidth()} / {@see self::measureBlocks()}.
     * A directly-constructed builder has none and those two methods then throw.
     */
    public function __construct(private readonly ?Measurer $measurer = null, ?Style $baseStyle = null)
    {
        $this->size = PageSize::a4();
        $this->marginsPt = Edges::all(28.35);
        $this->baseStyle = $baseStyle ?? Style::default();
    }

    /** The unit used by place*() coordinates (default: millimetres). */
    public function units(Unit $unit): self
    {
        $this->unit = $unit;

        return $this;
    }

    /** The unit currently set for this page's absolute-placement coordinates. */
    public function unit(): Unit
    {
        return $this->unit;
    }

    public function size(PageSize $size): self
    {
        $this->size = $size;

        return $this;
    }

    public function orientation(Orientation $orientation): self
    {
        $this->orientation = $orientation;

        return $this;
    }

    public function landscape(): self
    {
        $this->orientation = Orientation::Landscape;

        return $this;
    }

    public function margin(float $value, Unit $unit = Unit::Mm): self
    {
        $this->marginsPt = Edges::all($unit->toPoints($value));

        return $this;
    }

    public function margins(Edges $marginsPt): self
    {
        $this->marginsPt = $marginsPt;

        return $this;
    }

    /** @param \Closure(\Pdf\Layout\PageContext): (BlockNode|iterable<BlockNode>) $factory */
    public function header(\Closure $factory): self
    {
        $this->header = $factory;

        return $this;
    }

    /** @param \Closure(\Pdf\Layout\PageContext): (BlockNode|iterable<BlockNode>) $factory */
    public function footer(\Closure $factory): self
    {
        $this->footer = $factory;

        return $this;
    }

    /**
     * A page number in the footer (or header). `{n}` is the current page, `{N}`
     * the total. Shorthand for a `footer()` closure.
     */
    public function pageNumbers(
        string $format = 'Page {n} of {N}',
        TextAlign $align = TextAlign::Center,
        float $fontSizePt = 9.0,
        ?Color $color = null,
        bool $inHeader = false,
    ): self {
        $band = static fn (PageContext $c): Paragraph => new Paragraph(
            strtr($format, ['{n}' => (string) $c->pageNumber, '{N}' => (string) $c->pageCount]),
            new StylePatch(
                fontSizePt: $fontSizePt,
                color: $color ?? Color::gray(110),
                align: $align,
                spaceBeforePt: 0.0,
                spaceAfterPt: 0.0,
            ),
        );

        return $inHeader ? $this->header($band) : $this->footer($band);
    }

    /** Stamp a word across every sheet — `'DRAFT'`, or a configured {@see Watermark}. */
    public function watermark(string|Watermark $watermark): self
    {
        $this->watermark = $watermark instanceof Watermark ? $watermark : new Watermark($watermark);

        return $this;
    }

    /**
     * The transition a viewer plays when advancing to this page in
     * presentation mode — see the {@see Transition} factories. Applies to every
     * physical sheet the page flows across.
     */
    public function transition(Transition $transition): self
    {
        $this->transition = $transition;

        return $this;
    }

    /**
     * Auto-advance past this page after `$seconds` (`/Dur`). Mainstream viewers
     * only honour this in full-screen mode, so pair it with
     * {@see DocumentBuilder::presentation()}.
     */
    public function autoAdvance(float $seconds): self
    {
        $this->autoAdvanceSec = $seconds;

        return $this;
    }

    public function add(BlockNode $node): self
    {
        $this->children[] = $node;

        return $this;
    }

    public function heading(int $level, InlineSequence|string $content, StylePatch $patch = new StylePatch()): self
    {
        return $this->add(new Heading($level, $content, $patch));
    }

    public function paragraph(InlineSequence|string $content, StylePatch $patch = new StylePatch()): self
    {
        return $this->add(new Paragraph($content, $patch));
    }

    /** A paragraph whose content is a small subset of inline HTML. */
    public function html(string $html, StylePatch $patch = new StylePatch()): self
    {
        return $this->add(new Paragraph(Html::toInline($html), $patch));
    }

    public function spacer(float $height, Unit $unit = Unit::Mm): self
    {
        return $this->add(Spacer::of($height, $unit));
    }

    public function rule(float $thicknessPt = 0.5, ?Color $color = null): self
    {
        return $this->add(new Rule($thicknessPt, $color));
    }

    /** Add vector linework — see the {@see Path} factories for the common shapes. */
    public function path(Path $path): self
    {
        return $this->add($path);
    }

    /** Add a data chart — see {@see Chart::bar()} / `line()` / `pie()` / `sparkline()`. */
    public function chart(Chart $chart): self
    {
        return $this->add($chart);
    }

    /**
     * Clip a group of blocks to a {@see Path}'s region. The path is used for its
     * outline only, not painted — add it again with {@see self::path()} to draw
     * it.
     *
     * @param iterable<BlockNode> $children
     */
    public function clip(
        Path $path,
        iterable $children,
        FillRule $clipRule = FillRule::NonZero,
        StylePatch $patch = new StylePatch(),
    ): self {
        return $this->add(new Clip($path, $children, $clipRule, $patch));
    }

    /**
     * Add an interactive form field — a {@see \Pdf\Node\TextField},
     * {@see \Pdf\Node\Checkbox}, {@see \Pdf\Node\RadioGroup},
     * {@see \Pdf\Node\Dropdown}, {@see \Pdf\Node\ListBox},
     * {@see \Pdf\Node\PushButton} or {@see \Pdf\Node\SignatureField}.
     */
    public function field(FormField $field): self
    {
        return $this->add($field);
    }

    public function pageBreak(): self
    {
        return $this->add(new PageBreak());
    }

    public function anchor(string $name): self
    {
        return $this->add(new Anchor($name));
    }

    public function image(
        string $path,
        ?float $width = null,
        ?float $height = null,
        Unit $unit = Unit::Mm,
        TextAlign $align = TextAlign::Left,
    ): self {
        return $this->add(ImageBlock::of($path, $width, $height, $unit, $align));
    }

    /**
     * @param iterable<BlockNode> $children
     */
    public function container(iterable $children, StylePatch $patch = new StylePatch()): self
    {
        return $this->add(new Container($children, $patch));
    }

    /** Add a reusable {@see Component}; it expands to its `body()` during layout. */
    public function component(Component $component): self
    {
        return $this->add($component);
    }

    /**
     * @param iterable<BlockNode> $children
     */
    public function columns(iterable $children, int $count = 2, float $gutterPt = 14.0): self
    {
        return $this->add(new Columns($children, $count, $gutterPt));
    }

    /**
     * @param iterable<TableRow> $rows
     * @param list<ColumnWidth>|null $columns
     */
    public function table(
        iterable $rows,
        ?array $columns = null,
        int $headerRows = 0,
        ?float $totalWidthPt = null,
    ): self {
        return $this->add(new Table($rows, $columns, $totalWidthPt, $headerRows));
    }

    /** Add the {@see Table} a configured {@see DataTable} builds. */
    public function dataTable(DataTable $table): self
    {
        return $this->add($table->build());
    }

    /**
     * @param iterable<ListItem|string> $items
     */
    public function bulletList(iterable $items, StylePatch $patch = new StylePatch()): self
    {
        return $this->add(new BulletList($items, patch: $patch));
    }

    /**
     * @param iterable<ListItem|string> $items
     */
    public function orderedList(iterable $items, int $start = 1, StylePatch $patch = new StylePatch()): self
    {
        return $this->add(new OrderedList($items, start: $start, patch: $patch));
    }

    /**
     * Advance width of `$text` set on one unbroken line, in the page's units.
     *
     * No wrapping and no `\n`: this measures a single run. `$patch` resolves
     * against the document's base style, the same parent an inline run sees, so
     * the result agrees with the line breaker. Use it to right-align or offset
     * text in an absolute layout.
     */
    public function textWidth(string $text, StylePatch $patch = new StylePatch()): float
    {
        $style = $patch->applyTo($this->baseStyle);
        $widthPt = (new TextMeasurer($this->requireMeasurer()->fonts()))->width($text, $style);

        return $this->unit->fromPoints($widthPt);
    }

    /**
     * Natural stacked height of `$blocks` laid out at `$width`, in the page's
     * units — size a {@see self::place()} rectangle to its content, or drive a
     * caller's own shrink-to-fit loop.
     *
     * `$width` is taken in the page's units; the result is returned in them.
     *
     * @param iterable<BlockNode> $blocks
     */
    public function measureBlocks(iterable $blocks, float $width): float
    {
        $heightPt = $this->requireMeasurer()
            ->measureStack($blocks, $this->unit->toPoints($width), $this->baseStyle)
            ->contentHeightPt();

        return $this->unit->fromPoints($heightPt);
    }

    private function requireMeasurer(): Measurer
    {
        return $this->measurer ?? throw new LayoutException(
            'textWidth() / measureBlocks() need a renderer to resolve fonts; build the page via '
            . 'Pdf\Document::create()->page(...) instead of constructing PageBuilder directly.',
        );
    }

    /**
     * Place block content in an absolute rectangle (coordinates in the page's
     * units). Content flows within the width; when it is taller than the area,
     * `$shrink` decides how it is fitted — a uniform geometric scale (the
     * default), a smaller effective font size that re-wraps, or not at all.
     *
     * @param iterable<BlockNode> $blocks
     */
    public function place(
        float $x,
        float $y,
        float $width,
        float $height,
        iterable $blocks,
        BoxAlign $align = BoxAlign::TopLeft,
        ShrinkMode $shrink = ShrinkMode::Scale,
    ): self {
        $this->placements[] = new Placement(
            $this->rect($x, $y, $width, $height),
            new Blocks($blocks, $shrink),
            align: $align,
        );

        return $this;
    }

    /**
     * Place a raster image in an absolute rectangle with a fit mode. The source
     * may be a filesystem path, an `http(s)://` URL, or a `data:` URI.
     */
    public function placeImage(
        float $x,
        float $y,
        float $width,
        float $height,
        string $source,
        Fit $fit = Fit::Contain,
        BoxAlign $align = BoxAlign::Center,
    ): self {
        $this->placements[] = new Placement($this->rect($x, $y, $width, $height), new Picture($source), $fit, $align);

        return $this;
    }

    /**
     * Place a raster image already held in memory (e.g. fetched by the caller).
     * The bytes are carried inline as a `data:` URI, so prefer {@see placeImage}
     * with a path for anything large.
     */
    public function placeImageData(
        float $x,
        float $y,
        float $width,
        float $height,
        string $bytes,
        Fit $fit = Fit::Contain,
        BoxAlign $align = BoxAlign::Center,
    ): self {
        return $this->placeImage(
            $x,
            $y,
            $width,
            $height,
            'data:;base64,' . base64_encode($bytes),
            $fit,
            $align,
        );
    }

    /**
     * Place one page of an external PDF into an absolute rectangle as a vector
     * Form XObject.
     */
    public function placePdf(
        float $x,
        float $y,
        float $width,
        float $height,
        string $path,
        int $page = 1,
        Fit $fit = Fit::Contain,
        BoxAlign $align = BoxAlign::Center,
    ): self {
        $this->placements[] = new Placement($this->rect($x, $y, $width, $height), new PdfPage($path, $page), $fit, $align);

        return $this;
    }

    /**
     * The writable area — the page box inside the margins — in points, measured
     * from the top edge.
     *
     * A snapshot of the current {@see self::size()} / {@see self::orientation()}
     * / {@see self::margin()}: call it after those are set. It is margins-only —
     * it does not subtract any {@see self::header()} / {@see self::footer()}
     * band, so a grid built from it can overlap a header or footer. Absolute
     * placements draw over the flow content regardless, so this is usually fine
     * for a full-sheet layout; inset the rectangle yourself if you keep a band.
     */
    public function writableRectPt(): Rect
    {
        return (new PageMaster($this->size, $this->orientation, $this->marginsPt))
            ->geometry()
            ->contentBox();
    }

    /**
     * A {@see \Pdf\Layout\Grid} over the writable area (see {@see
     * self::writableRectPt()} for its caveats). `$gutterPt` is the gap left
     * between sibling slices, in points.
     */
    public function grid(float $gutterPt = 0.0): Grid
    {
        return Grid::inside($this->writableRectPt(), $gutterPt);
    }

    /**
     * A horizontal hairline `$length` long whose top edge starts at `(x, y)`
     * (corner origin, not centreline) — a divider rule in an absolute layout.
     * `$stroke` is the line thickness in points, or a {@see Border} whose widest
     * edge and colour are used. `x`, `y` and `length` honour {@see self::units()}.
     */
    public function hline(float $x, float $y, float $length, Border|float $stroke = 0.5): self
    {
        [$thicknessPt, $color] = $this->strokeSpec($stroke);
        $this->placements[] = new Placement(
            new Rect($this->unit->toPoints($x), $this->unit->toPoints($y), $this->unit->toPoints($length), $thicknessPt),
            new Frame(background: $color),
        );

        return $this;
    }

    /**
     * A vertical hairline `$length` long whose left edge starts at `(x, y)`
     * (corner origin, not centreline). See {@see self::hline()}.
     */
    public function vline(float $x, float $y, float $length, Border|float $stroke = 0.5): self
    {
        [$thicknessPt, $color] = $this->strokeSpec($stroke);
        $this->placements[] = new Placement(
            new Rect($this->unit->toPoints($x), $this->unit->toPoints($y), $thicknessPt, $this->unit->toPoints($length)),
            new Frame(background: $color),
        );

        return $this;
    }

    /** @return array{0: float, 1: Color} thickness in points, and the line colour */
    private function strokeSpec(Border|float $stroke): array
    {
        if ($stroke instanceof Border) {
            $w = $stroke->widthPt;

            return [max($w->top, $w->right, $w->bottom, $w->left), $stroke->color];
        }

        return [$stroke, new Color(0, 0, 0)];
    }

    /** Draw a bordered/filled rectangle at an absolute area (sheet borders, cells). */
    public function frame(
        float $x,
        float $y,
        float $width,
        float $height,
        Border $border = new Border(),
        ?Color $background = null,
    ): self {
        $this->placements[] = new Placement($this->rect($x, $y, $width, $height), new Frame($border, $background));

        return $this;
    }

    private function rect(float $x, float $y, float $width, float $height): Rect
    {
        return new Rect(
            $this->unit->toPoints($x),
            $this->unit->toPoints($y),
            $this->unit->toPoints($width),
            $this->unit->toPoints($height),
        );
    }

    public function build(): Page
    {
        $master = new PageMaster(
            $this->size,
            $this->orientation,
            $this->marginsPt,
            $this->header,
            $this->footer,
            $this->watermark,
            $this->transition,
            $this->autoAdvanceSec,
        );

        return new Page($master, $this->children, placements: $this->placements);
    }
}
