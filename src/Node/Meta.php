<?php

declare(strict_types=1);

namespace Pdf\Node;

/**
 * Document metadata written into the Info dictionary.
 *
 * Ports `SetTitle()` / `SetAuthor()` / `SetSubject()` / `SetKeywords()` /
 * `SetCreator()` (fpdf.php:228-256). `Producer` is set by the renderer.
 */
final readonly class Meta
{
    public function __construct(
        public ?string $title = null,
        public ?string $author = null,
        public ?string $subject = null,
        public ?string $keywords = null,
        public ?string $creator = null,
    ) {
    }

    /** @return array<string, string> */
    public function entries(): array
    {
        return array_filter([
            'Title' => $this->title,
            'Author' => $this->author,
            'Subject' => $this->subject,
            'Keywords' => $this->keywords,
            'Creator' => $this->creator,
        ], static fn (?string $v) => $v !== null && $v !== '');
    }
}
