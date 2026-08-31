<?php

declare(strict_types=1);

namespace Pdf\Tests\Unit;

use Pdf\Exception\PdfException;
use Pdf\Support\Source;
use PHPUnit\Framework\TestCase;

final class SourceTest extends TestCase
{
    private string $file = '';

    protected function setUp(): void
    {
        $this->file = tempnam(sys_get_temp_dir(), 'src') ?: '';
        file_put_contents($this->file, 'x');
    }

    protected function tearDown(): void
    {
        @unlink($this->file);
    }

    public function test_returns_the_first_existing_file(): void
    {
        self::assertSame(
            $this->file,
            Source::first([null, '', '/no/such/path-abc', $this->file]),
        );
    }

    public function test_skips_a_missing_file_and_falls_back(): void
    {
        self::assertSame(
            'placeholder.png',
            Source::first(['/no/such/file', 'ftp://unsupported/scheme'], static fn (): string => 'placeholder.png'),
        );
    }

    public function test_throws_when_nothing_matches_and_no_fallback(): void
    {
        $this->expectException(PdfException::class);

        Source::first(['/no/such/file', null]);
    }

    public function test_non_http_scheme_is_not_treated_as_a_url(): void
    {
        $this->expectException(PdfException::class);

        Source::first(['file:///etc/hostname', 's3://bucket/key']);
    }
}
