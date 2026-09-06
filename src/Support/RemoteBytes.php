<?php

declare(strict_types=1);

namespace Pdf\Support;

use Pdf\Exception\PdfException;

/**
 * Fetches the bytes behind an `http(s)://` URL, using the streams layer and
 * falling back to `ext-curl` when `allow_url_fopen` is off — no Composer
 * dependency either way.
 *
 * Shared by the image and SVG loaders, which each wrap a failure in their own
 * exception type. A fetch runs during layout: pass a URL only when you trust
 * it (there is no SSRF guard) and accept that output stops being byte-
 * deterministic if the resource changes.
 */
final class RemoteBytes
{
    private const FETCH_TIMEOUT_SECONDS = 10;
    private const MAX_REDIRECTS = 5;

    /** @param string $accept the Accept header to send, e.g. `image/*` */
    public static function fetch(string $url, string $accept): string
    {
        $bytes = self::viaStreams($url, $accept);

        if ($bytes === null && function_exists('curl_exec')) {
            $bytes = self::viaCurl($url);
        }

        if ($bytes === null || $bytes === '') {
            throw new PdfException(sprintf(
                'Could not fetch %s. If allow_url_fopen is off and ext-curl is unavailable, '
                . 'fetch the bytes yourself and pass a data: URI instead.',
                $url,
            ));
        }

        return $bytes;
    }

    private static function viaStreams(string $url, string $accept): ?string
    {
        if (!filter_var($url, FILTER_VALIDATE_URL)) {
            throw new PdfException('Not a valid URL: ' . $url);
        }

        $options = [
            'method' => 'GET',
            'timeout' => self::FETCH_TIMEOUT_SECONDS,
            'follow_location' => 1,
            'max_redirects' => self::MAX_REDIRECTS,
            'header' => 'Accept: ' . $accept . "\r\nUser-Agent: declarative-pdf\r\n",
            'ignore_errors' => true,
        ];

        $context = stream_context_create([
            'http' => $options,
            'https' => $options,
            'ssl' => ['verify_peer' => true, 'verify_peer_name' => true],
        ]);

        $bytes = @file_get_contents($url, false, $context);
        if ($bytes === false) {
            return null;
        }

        // $http_response_header is populated by the HTTP(S) stream wrapper.
        if (self::isHttpError($http_response_header)) {
            throw new PdfException('URL returned an HTTP error: ' . $url);
        }

        return $bytes;
    }

    private static function viaCurl(string $url): ?string
    {
        $handle = curl_init($url);
        if ($handle === false) {
            return null;
        }

        curl_setopt_array($handle, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_MAXREDIRS => self::MAX_REDIRECTS,
            CURLOPT_TIMEOUT => self::FETCH_TIMEOUT_SECONDS,
            CURLOPT_CONNECTTIMEOUT => 5,
            CURLOPT_USERAGENT => 'declarative-pdf',
        ]);

        $body = curl_exec($handle);
        $status = curl_getinfo($handle, CURLINFO_RESPONSE_CODE);
        curl_close($handle);

        if (!is_string($body) || $status >= 400) {
            return null;
        }

        return $body;
    }

    /** @param list<string> $headers */
    private static function isHttpError(array $headers): bool
    {
        $status = 0;
        foreach ($headers as $header) {
            if (preg_match('#^HTTP/\S+\s+(\d{3})#', $header, $match) === 1) {
                $status = (int) $match[1];
            }
        }

        return $status >= 400;
    }
}
