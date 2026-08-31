<?php

declare(strict_types=1);

namespace Pdf\Builder;

use Pdf\Color\Color;
use Pdf\Geometry\Edges;
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
 * Title, subtitle, an optional logo and any number of caption lines (date,
 * author, reference) are arranged by one of three {@see CoverLayout} presets.
 * The presets are plain flow content — spacers and a container — so they hold
 * up at any page size; vertical placement is approximate, tuned for A4/Letter
 * portrait.
 */
final class CoverBuilder
{
    private const NAVY = [19, 33, 68];

    private ?string $title = null;
    private ?string $subtitle = null;
    private ?string $logoPath = null;

    /** @var list<string> */
    private array $lines = [];

    private CoverLayout $layout = CoverLayout::Centered;

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

    /** @return \Closure(PageBuilder): void */
    public function pageConfigurator(): \Closure
    {
        return function (PageBuilder $page): void {
            foreach ($this->blocks() as $block) {
                $page->add($block);
            }
        };
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

        $blocks = [$this->spacer(78.0)];
        if ($this->logoPath !== null) {
            $blocks[] = new ImageBlock($this->logoPath, heightPt: Unit::Mm->toPoints(24.0), align: TextAlign::Center);
            $blocks[] = $this->spacer(12.0);
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

        $blocks = [$this->spacer(40.0)];
        if ($this->logoPath !== null) {
            $blocks[] = new ImageBlock($this->logoPath, heightPt: Unit::Mm->toPoints(18.0));
            $blocks[] = $this->spacer(10.0);
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
            $this->spacer(205.0),
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

    private function spacer(float $millimetres): Spacer
    {
        return new Spacer(Unit::Mm->toPoints($millimetres));
    }
}
