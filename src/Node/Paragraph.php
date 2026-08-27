<?php

declare(strict_types=1);

namespace Pdf\Node;

use Pdf\Style\StylePatch;
use Pdf\Text\InlineSequence;

final readonly class Paragraph implements BlockNode
{
    public InlineSequence $content;

    public function __construct(
        InlineSequence|string $content,
        private StylePatch $patch = new StylePatch(),
    ) {
        $this->content = $content instanceof InlineSequence
            ? $content
            : InlineSequence::of($content);
    }

    public function patch(): StylePatch
    {
        return $this->patch;
    }
}
