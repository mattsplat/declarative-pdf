<?php

declare(strict_types=1);

namespace Pdf\Tests\Support;

use PHPUnit\Framework\Assert;

/**
 * Byte-exact golden-file comparison. Set UPDATE_GOLDENS=1 to (re)write.
 */
final class Golden
{
    public static function assert(string $name, string $actual): void
    {
        $path = dirname(__DIR__) . '/golden/' . $name;

        if (getenv('UPDATE_GOLDENS') === '1' || !is_file($path)) {
            @mkdir(dirname($path), 0o777, true);
            file_put_contents($path, $actual);
        }

        Assert::assertSame(
            file_get_contents($path),
            $actual,
            "Golden mismatch for {$name}. Run with UPDATE_GOLDENS=1 to refresh.",
        );
    }
}
