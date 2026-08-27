<?php

declare(strict_types=1);

namespace Pdf\Support;

final class FixedClock implements Clock
{
    public function __construct(private readonly \DateTimeImmutable $instant)
    {
    }

    public static function at(string $iso8601): self
    {
        return new self(new \DateTimeImmutable($iso8601));
    }

    public function now(): \DateTimeImmutable
    {
        return $this->instant;
    }
}
