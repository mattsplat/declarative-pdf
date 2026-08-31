<?php

declare(strict_types=1);

namespace Pdf\Builder;

use Pdf\Color\Color;
use Pdf\Geometry\Edges;
use Pdf\Geometry\Orientation;
use Pdf\Geometry\PageSize;
use Pdf\Geometry\Unit;
use Pdf\Node\BlockNode;
use Pdf\Node\Container;
use Pdf\Node\Heading;
use Pdf\Node\ImageBlock;
use Pdf\Node\Paragraph;
use Pdf\Node\Row;
use Pdf\Node\Spacer;
use Pdf\Style\StylePatch;
use Pdf\Style\TextAlign;
use Pdf\Style\VerticalAlign;

/**
 * Configures the cover page prepended by {@see DocumentBuilder::cover()}.
 *
 * A title, subtitle, optional logo and any number of caption lines (date,
 * author, reference) are arranged by one of three {@see CoverLayout} presets.
 * The cover has its own page size (default A4 portrait — set it with
 * {@see self::size()} / {@see self::landscape()} to match the rest of the
 * document); the presets measure the actual content box and position their
 * block vertically from it, so they adapt to any size and never spill onto a
 * second sheet.
 *
 * By default the cover keeps a document-wide watermark but drops document-wide
 * page numbers (a "1 / N" on a cover reads wrong); {@see self::bare()} drops
 * both.
 */
final class CoverBuilder
{
    /** Matches {@see PageBuilder}'s default margin. */
    private const MARGIN_PT = 28.35;

    private const NAVY = [19, 33, 68];

    private ?string $title = null;
    private ?string $subtitle = null;
    private ?string $logoPath = null;

    /** @var list<string> */
    private array $lines = [];

    private CoverLayout $layout = CoverLayout::Centered;
    private PageSize $size;
    private Orientation $orientation = Orientation::Portrait;
    private bool $bare = false;

    public function __construct()
    {
        $this->size = PageSize::a4();
    }

    public function title(string $title): self
    {
        $this->title = $title;

        return $this;
    }

    public function subtitle(string $subtitle): self
    {
        $this->subtitle = $subtitle;

        return $this;
    }

    public function logo(string $path): self
    {
        $this->logoPath = $path;

        return $this;
    }

    /** A caption line under the title — call again, or pass several, to stack them. */
    public function line(string ...$lines): self
    {
        foreach ($lines as $line) {
            $this->lines[] = $line;
        }

        return $this;
    }

    public function layout(CoverLayout $layout): self
    {
        $this->layout = $layout;

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

    /** Drop both inherited page numbers and the inherited watermark from the cover. */
    public function bare(bool $bare = true): self
    {
        $this->bare = $bare;

        return $this;
    }

    /**
     * Whether the cover opts into the document-level `$kind` furniture.
     *
     * @internal called by {@see DocumentBuilder}
     */
    public function wants(string $kind): bool
    {
        if ($this->bare) {
            return false;
        }

        return $kind === 'watermark';
    }

    /**
     * Lay the cover onto a fresh page.
     *
     * @internal called by {@see DocumentBuilder::cover()}
     */
    public function configure(PageBuilder $page): void
    {
        $page->size($this->size)->orientation($this->orientation)->units(Unit::Pt);

        $resolved = $this->size->forOrientation($this->orientation);
        $availPt = $resolved->heightPt - 2 * self::MARGIN_PT;
        $widthPt = $resolved->widthPt - 2 * self::MARGIN_PT;

        $blocks = $this->blocks();
        $contentPt = $page->measureBlocks($blocks, $widthPt);

        $page->add(new Spacer($this->leadingPt($availPt, $contentPt)));
        foreach ($blocks as $block) {
            $page->add($block);
        }
    }

    private function leadingPt(float $availPt, float $contentPt): float
    {
        $slackPt = max(0.0, $availPt - $contentPt - 2.0);
        $wantPt = match ($this->layout) {
            CoverLayout::Centered => ($availPt - $contentPt) / 2.0,
            CoverLayout::TopLeft => Unit::Mm->toPoints(6.0),
            CoverLayout::BottomBand => $slackPt,
        };

        return max(0.0, min($wantPt, $slackPt));
    }

    /** @return list<BlockNode> */
    private function blocks(): array
    {
        return match ($this->layout) {
            CoverLayout::Centered => $this->centered(),
            CoverLayout::TopLeft => $this->topLeft(),
            CoverLayout::BottomBand => $this->bottomBand(),
        };
    }

    /** @return list<BlockNode> */
    private function centered(): array
    {
        $navy = Color::rgb(...self::NAVY);
        $muted = Color::gray(115);

        $blocks = [];
        if ($this->logoPath !== null) {
            $blocks[] = new ImageBlock($this->logoPath, heightPt: Unit::Mm->toPoints(24.0), align: TextAlign::Center);
            $blocks[] = new Spacer(Unit::Mm->toPoints(12.0));
        }
        if ($this->title !== null) {
            $blocks[] = new Heading(1, $this->title, new StylePatch(
                fontSizePt: 34.0,
                color: $navy,
                align: TextAlign::Center,
                spaceAfterPt: 6.0,
            ));
        }
        if ($this->subtitle !== null) {
            $blocks[] = new Paragraph($this->subtitle, new StylePatch(
                fontSizePt: 13.0,
                color: $muted,
                align: TextAlign::Center,
                spaceAfterPt: 0.0,
            ));
        }
        foreach ($this->captionBlocks(TextAlign::Center, 16.0) as $block) {
            $blocks[] = $block;
        }

        return $blocks;
    }

    /** @return list<BlockNode> */
    private function topLeft(): array
    {
        $navy = Color::rgb(...self::NAVY);
        $muted = Color::gray(115);

        $blocks = [];
        if ($this->logoPath !== null) {
            $blocks[] = new ImageBlock($this->logoPath, heightPt: Unit::Mm->toPoints(18.0));
            $blocks[] = new Spacer(Unit::Mm->toPoints(10.0));
        }
        if ($this->title !== null) {
            $blocks[] = new Heading(1, $this->title, new StylePatch(fontSizePt: 30.0, color: $navy, spaceAfterPt: 6.0));
        }
        if ($this->subtitle !== null) {
            $blocks[] = new Paragraph($this->subtitle, new StylePatch(
                fontSizePt: 13.0,
                color: $muted,
                spaceAfterPt: 0.0,
            ));
        }
        foreach ($this->captionBlocks(TextAlign::Left, 14.0) as $block) {
            $blocks[] = $block;
        }

        return $blocks;
    }

    /** @return list<BlockNode> */
    private function bottomBand(): array
    {
        $band = Color::rgb(...self::NAVY);

        $inner = [];
        if ($this->logoPath !== null && $this->title !== null) {
            $inner[] = new Row([
                new ImageBlock($this->logoPath, heightPt: Unit::Mm->toPoints(14.0)),
                new Heading(1, $this->title, new StylePatch(fontSizePt: 24.0, color: Color::white(), spaceAfterPt: 0.0)),
            ], gapPt: 10.0, align: VerticalAlign::Middle);
        } elseif ($this->title !== null) {
            $inner[] = new Heading(1, $this->title, new StylePatch(
                fontSizePt: 28.0,
                color: Color::white(),
                spaceAfterPt: 0.0,
            ));
        }
        if ($this->subtitle !== null) {
            $inner[] = new Paragraph($this->subtitle, new StylePatch(
                fontSizePt: 12.0,
                color: Color::rgb(198, 210, 230),
                spaceBeforePt: 6.0,
                spaceAfterPt: 0.0,
            ));
        }
        if ($this->lines !== []) {
            $inner[] = new Paragraph(implode('     ·     ', $this->lines), new StylePatch(
                fontSizePt: 9.0,
                color: Color::rgb(168, 184, 212),
                spaceBeforePt: 10.0,
                spaceAfterPt: 0.0,
            ));
        }

        return [
            new Container($inner, new StylePatch(
                paddingPt: Edges::symmetric(18.0, 20.0),
                background: $band,
            )),
        ];
    }

    /**
     * @return list<BlockNode>
     */
    private function captionBlocks(TextAlign $align, float $spaceBeforePt): array
    {
        if ($this->lines === []) {
            return [];
        }

        return [
            new Paragraph(implode('     ·     ', $this->lines), new StylePatch(
                fontSizePt: 9.5,
                color: Color::gray(115),
                align: $align,
                spaceBeforePt: $spaceBeforePt,
            )),
        ];
    }
}
