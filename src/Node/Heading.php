<?php

declare(strict_types=1);

namespace Pdf\Node;

use Pdf\Exception\PdfException;
use Pdf\Style\StylePatch;
use Pdf\Text\InlineSequence;

final readonly class Heading implements BlockNode
{
    public InlineSequence $content;

    public function __construct(
        public int $level,
        InlineSequence|string $content,
        private StylePatch $patch = new StylePatch(),
    ) {
        if ($level < 1 || $level > 6) {
            throw new PdfException('Heading level must be between 1 and 6.');
        }
        $this->content = $content instanceof InlineSequence
            ? $content
            : InlineSequence::of($content);
    }

    public function patch(): StylePatch
    {
        return $this->patch;
    }
}
