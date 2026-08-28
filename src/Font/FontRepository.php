<?php

declare(strict_types=1);

namespace Pdf\Font;

use Pdf\Exception\FontException;

/**
 * Resolves a (family, face) pair to a {@see FontDefinition}, loading and
 * caching definition files on demand.
 *
 * Replaces FPDF's `FPDF_FONTPATH` constant (fpdf.php:105) with an injected
 * directory, and ports the core-font handling of `SetFont()` (fpdf.php:500-513):
 * `arial` is an alias for `helvetica`; `symbol` and `zapfdingbats` ignore the
 * requested style.
 *
 * A `register()`ed font wins over the alias — registering an actual "Arial"
 * embeds that file rather than falling back to bundled Helvetica.
 */
final class FontRepository
{
    private const CORE_FAMILIES = ['courier', 'helvetica', 'times', 'symbol', 'zapfdingbats'];
    private const STYLELESS_FAMILIES = ['symbol', 'zapfdingbats'];

    /** @var array<string, FontDefinition> */
    private array $cache = [];

    /** @var array<string, string> resolution key => definition file path */
    private array $registrations = [];

    public function __construct(
        private readonly string $fontDirectory,
        private readonly FontLoader $loader = new FontLoader(),
    ) {
    }

    public static function withBundledFonts(): self
    {
        return new self(dirname(__DIR__, 2) . '/resources/fonts');
    }

    /** Register a custom font definition file for one cut of a family. */
    public function register(string $family, FontFace $face, string $definitionPath): void
    {
        $this->registrations[$this->key($family, $face)] = $definitionPath;
        $this->forgetFamily($family); // a later registration must take effect
    }

    public function resolve(string $family, FontFace $face): FontDefinition
    {
        $requested = strtolower(trim($family));

        // A registration for exactly what was asked wins, before any aliasing.
        $registered = $this->registrations[$this->key($requested, $face)] ?? null;

        $resolvedFamily = $requested === 'arial' ? 'helvetica' : $requested;
        $resolvedFace = in_array($resolvedFamily, self::STYLELESS_FAMILIES, true)
            ? FontFace::regular()
            : $face;

        $cacheKey = $this->key($resolvedFamily, $resolvedFace);
        if ($registered === null && isset($this->cache[$cacheKey])) {
            return $this->cache[$cacheKey];
        }

        $path = $registered
            ?? $this->registrations[$cacheKey]
            ?? $this->corePath($resolvedFamily, $resolvedFace);

        if ($path === null) {
            throw new FontException(sprintf('Undefined font: %s %s', $requested, $face->describe()));
        }

        $definition = $this->loader->load($path);
        $this->cache[$cacheKey] = $definition;
        if ($registered !== null) {
            $this->cache[$this->key($requested, $face)] = $definition;
        }

        return $definition;
    }

    private function corePath(string $family, FontFace $face): ?string
    {
        if (!in_array($family, self::CORE_FAMILIES, true)) {
            return null;
        }

        $suffix = FontStyle::of($face->isBold(), $face->italic)->fileSuffix();

        return sprintf('%s/%s%s.json', $this->fontDirectory, $family, $suffix);
    }

    private function forgetFamily(string $family): void
    {
        $prefix = strtolower(trim($family)) . ':';
        foreach (array_keys($this->cache) as $key) {
            if (str_starts_with($key, $prefix)) {
                unset($this->cache[$key]);
            }
        }
    }

    private function key(string $family, FontFace $face): string
    {
        return strtolower(trim($family)) . ':' . $face->key();
    }
}
