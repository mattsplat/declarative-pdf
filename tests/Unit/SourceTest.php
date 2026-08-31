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

    public function test_an_unreachable_url_degrades_to_the_next_candidate(): void
    {
        // `.invalid` never resolves (RFC 6761): get_headers() returns false and
        // the resolver moves on rather than propagating the failure.
        self::assertSame(
            $this->file,
            Source::first(['https://nonexistent.invalid/asset.png', $this->file]),
        );
    }

    public function test_status_classification_of_a_head_response(): void
    {
        self::assertTrue(self::classify(['HTTP/1.1 200 OK']), 'plain 2xx');
        self::assertTrue(self::classify(['HTTP/2 204']), 'reason phrase optional');
        self::assertFalse(self::classify(['HTTP/1.1 404 Not Found']));
        self::assertFalse(self::classify(['HTTP/1.1 500 Internal Server Error']));

        // A redirected request yields a list of status lines; the last one wins.
        self::assertTrue(self::classify([['HTTP/1.1 301 Moved Permanently', 'HTTP/1.1 200 OK']]));
        self::assertFalse(self::classify([['HTTP/1.1 302 Found', 'HTTP/1.1 500 Error']]));

        // false == timeout / DNS failure / allow_url_fopen disabled.
        self::assertFalse(self::classify(false));
        self::assertFalse(self::classify([]));
    }

    /**
     * @param array<int|string, string|list<string>>|false $headers
     */
    private static function classify(array|false $headers): bool
    {
        return (new \ReflectionMethod(Source::class, 'statusIsSuccessful'))->invoke(null, $headers);
    }
}
