<?php

declare(strict_types=1);

namespace Pdf\Support;

use Pdf\Exception\PdfException;

/**
 * Resolves the first usable asset from a list of candidates — a local file
 * path, or an `http(s)://` URL that answers a HEAD request with a 2xx status.
 *
 * Captures the "prefer the live asset, fall back to a bundled copy" pattern the
 * sheet examples use to resolve a drawing or nameplate. The URL pre-flight uses
 * a short timeout so an offline or slow network never hangs a render.
 */
final class Source
{
    /**
     * @param list<string|null> $candidates tried in order; null / empty entries are skipped
     * @param (callable(): string)|null $fallback produces a path or URI when nothing matched
     */
    public static function first(array $candidates, ?callable $fallback = null): string
    {
        foreach ($candidates as $candidate) {
            if ($candidate !== null && $candidate !== '' && self::isUsable($candidate)) {
                return $candidate;
            }
        }

        if ($fallback !== null) {
            return $fallback();
        }

        throw new PdfException('No usable source among ' . count($candidates) . ' candidate(s), and no fallback given.');
    }

    private static function isUsable(string $candidate): bool
    {
        if (preg_match('#^https?://#i', $candidate) === 1) {
            return self::urlAnswers($candidate);
        }

        // Any other URI scheme is something this resolver does not handle;
        // never hand it to is_file(), which warns on unknown stream wrappers.
        if (preg_match('#^[a-z][a-z0-9+.\-]*://#i', $candidate) === 1) {
            return false;
        }

        return is_file($candidate);
    }

    private static function urlAnswers(string $url, float $timeoutSeconds = 2.0): bool
    {
        $context = stream_context_create(['http' => ['method' => 'HEAD', 'timeout' => $timeoutSeconds]]);

        return self::statusIsSuccessful(@get_headers($url, true, $context));
    }

    /**
     * Whether a `get_headers()` result carries a 2xx status. A `false` result
     * (timeout, DNS failure, `allow_url_fopen` disabled) is treated as "no".
     * `$headers[0]` is a list of status lines when the request was redirected.
     *
     * @param array<int|string, string|list<string>>|false $headers
     */
    private static function statusIsSuccessful(array|false $headers): bool
    {
        if ($headers === false) {
            return false;
        }

        $status = $headers[0] ?? '';
        if (is_array($status)) {
            $status = end($status);
        }

        return is_string($status) && preg_match('#\s2\d\d(\s|$)#', $status) === 1;
    }
}
