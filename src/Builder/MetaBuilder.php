<?php

declare(strict_types=1);

namespace Pdf\Builder;

use Pdf\Node\Meta;

final class MetaBuilder
{
    private ?string $title = null;
    private ?string $author = null;
    private ?string $subject = null;
    private ?string $keywords = null;
    private ?string $creator = null;

    public function title(string $title): self
    {
        $this->title = $title;

        return $this;
    }

    public function author(string $author): self
    {
        $this->author = $author;

        return $this;
    }

    public function subject(string $subject): self
    {
        $this->subject = $subject;

        return $this;
    }

    public function keywords(string $keywords): self
    {
        $this->keywords = $keywords;

        return $this;
    }

    public function creator(string $creator): self
    {
        $this->creator = $creator;

        return $this;
    }

    public function build(): Meta
    {
        return new Meta($this->title, $this->author, $this->subject, $this->keywords, $this->creator);
    }
}
