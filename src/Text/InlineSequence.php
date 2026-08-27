<?php

declare(strict_types=1);

namespace Pdf\Text;

use Pdf\Style\StylePatch;

/**
 * An ordered sequence of styled text runs — the inline content of a block.
 *
 * Carriage returns are stripped (as in `MultiCell()`, fpdf.php:670); newlines
 * are kept and become explicit line breaks during layout.
 */
final readonly class InlineSequence
{
    /** @param list<TextRun> $runs */
    public function __construct(public array $runs)
    {
    }

    public static function of(string $text): self
    {
        return new self([new TextRun(self::normalize($text))]);
    }

    public static function empty(): self
    {
        return new self([]);
    }

    /** @param list<TextRun> $runs */
    public static function fromRuns(array $runs): self
    {
        return new self(array_map(
            static fn (TextRun $r) => new TextRun(self::normalize($r->text), $r->patch),
            $runs,
        ));
    }

    public function withRun(string $text, StylePatch $patch = new StylePatch()): self
    {
        return new self([...$this->runs, new TextRun(self::normalize($text), $patch)]);
    }

    /**
     * Append a linked run. `$target` is a URI, or `#name` for an internal
     * {@see \Pdf\Node\Anchor}. A default underlined-blue style is applied
     * unless overridden.
     */
    public function withLink(string $text, string $target, ?StylePatch $patch = null): self
    {
        $patch ??= new StylePatch(color: new \Pdf\Color\Color(0, 0, 238), underline: true);

        return new self([...$this->runs, new TextRun(self::normalize($text), $patch, $target)]);
    }

    public function withBold(string $text): self
    {
        return $this->withRun($text, new StylePatch(bold: true));
    }

    public function withItalic(string $text): self
    {
        return $this->withRun($text, new StylePatch(italic: true));
    }

    public function withUnderline(string $text): self
    {
        return $this->withRun($text, new StylePatch(underline: true));
    }

    public function withStrikethrough(string $text): self
    {
        return $this->withRun($text, new StylePatch(strikethrough: true));
    }

    public function withSuperscript(string $text): self
    {
        return $this->withRun($text, StylePatch::superscript());
    }

    public function withSubscript(string $text): self
    {
        return $this->withRun($text, StylePatch::subscript());
    }

    /** A hard line break (`<br>`) without ending the paragraph. */
    public function withBreak(): self
    {
        return new self([...$this->runs, new TextRun("\n")]);
    }

    /**
     * An inline image that flows with the text. With no dimensions the
     * intrinsic pixel size at 96 dpi is used; one dimension is derived from the
     * other to keep the aspect ratio.
     */
    public function withImage(
        string $path,
        ?float $width = null,
        ?float $height = null,
        \Pdf\Geometry\Unit $unit = \Pdf\Geometry\Unit::Mm,
    ): self {
        return new self([...$this->runs, new TextRun(
            text: '',
            imagePath: $path,
            imageWidthPt: $width !== null ? $unit->toPoints($width) : null,
            imageHeightPt: $height !== null ? $unit->toPoints($height) : null,
        )]);
    }

    public function isEmpty(): bool
    {
        foreach ($this->runs as $run) {
            if ($run->text !== '') {
                return false;
            }
        }

        return true;
    }

    public function plainText(): string
    {
        return implode('', array_map(static fn (TextRun $r) => $r->text, $this->runs));
    }

    private static function normalize(string $text): string
    {
        return str_replace("\r", '', $text);
    }
}
