<?php

declare(strict_types=1);

namespace Pdf\Font;

use Pdf\Exception\FontException;

/**
 * Resolves a (family, style) pair to a {@see FontDefinition}, loading and
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

    /** Register a custom font definition file for a family/style. */
    public function register(string $family, FontStyle $style, string $definitionPath): void
    {
        $key = $this->key($family, $style);
        $this->registrations[$key] = $definitionPath;
        unset($this->cache[$key]); // a later registration must take effect
    }

    public function resolve(string $family, FontStyle $style): FontDefinition
    {
        $requested = strtolower(trim($family));

        // A registration for exactly what was asked wins, before any aliasing.
        $registered = $this->registrations[$this->key($requested, $style)] ?? null;

        $resolvedFamily = $requested === 'arial' ? 'helvetica' : $requested;
        $resolvedStyle = in_array($resolvedFamily, self::STYLELESS_FAMILIES, true)
            ? FontStyle::Regular
            : $style;

        $cacheKey = $this->key($resolvedFamily, $resolvedStyle);
        if ($registered === null && isset($this->cache[$cacheKey])) {
            return $this->cache[$cacheKey];
        }

        $path = $registered
            ?? $this->registrations[$cacheKey]
            ?? $this->corePath($resolvedFamily, $resolvedStyle);

        if ($path === null) {
            throw new FontException(sprintf('Undefined font: %s %s', $requested, $style->name));
        }

        $definition = $this->loader->load($path);
        $this->cache[$cacheKey] = $definition;
        if ($registered !== null) {
            $this->cache[$this->key($requested, $style)] = $definition;
        }

        return $definition;
    }

    private function corePath(string $family, FontStyle $style): ?string
    {
        if (!in_array($family, self::CORE_FAMILIES, true)) {
            return null;
        }

        return sprintf('%s/%s%s.json', $this->fontDirectory, $family, $style->fileSuffix());
    }

    private function key(string $family, FontStyle $style): string
    {
        return strtolower(trim($family)) . ':' . $style->name;
    }
}
