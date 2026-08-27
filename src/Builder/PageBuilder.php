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
use Pdf\Geometry\Unit;
use Pdf\Node\Anchor;
use Pdf\Node\BlockNode;
use Pdf\Node\BulletList;
use Pdf\Node\Columns;
use Pdf\Node\Container;
use Pdf\Node\Heading;
use Pdf\Node\ImageBlock;
use Pdf\Node\ListItem;
use Pdf\Node\OrderedList;
use Pdf\Node\Page;
use Pdf\Node\PageBreak;
use Pdf\Node\PageMaster;
use Pdf\Node\Paragraph;
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
use Pdf\Style\StylePatch;
use Pdf\Style\TextAlign;
use Pdf\Text\Html;
use Pdf\Text\InlineSequence;

final class PageBuilder
{
    private PageSize $size;
    private Orientation $orientation = Orientation::Portrait;
    private Edges $marginsPt;
    private ?\Closure $header = null;
    private ?\Closure $footer = null;

    /** @var list<BlockNode> */
    private array $children = [];

    /** @var list<Placement> */
    private array $placements = [];

    private Unit $unit = Unit::Mm;

    public function __construct()
    {
        $this->size = PageSize::a4();
        $this->marginsPt = Edges::all(28.35);
    }

    /** The unit used by place*() coordinates (default: millimetres). */
    public function units(Unit $unit): self
    {
        $this->unit = $unit;

        return $this;
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
     * Place block content in an absolute rectangle (coordinates in the page's
     * units). Content flows within the width and is scaled down to fit the
     * height if necessary.
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
    ): self {
        $this->placements[] = new Placement($this->rect($x, $y, $width, $height), new Blocks($blocks), align: $align);

        return $this;
    }

    /** Place a raster image in an absolute rectangle with a fit mode. */
    public function placeImage(
        float $x,
        float $y,
        float $width,
        float $height,
        string $path,
        Fit $fit = Fit::Contain,
        BoxAlign $align = BoxAlign::Center,
    ): self {
        $this->placements[] = new Placement($this->rect($x, $y, $width, $height), new Picture($path), $fit, $align);

        return $this;
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
        );

        return new Page($master, $this->children, placements: $this->placements);
    }
}
