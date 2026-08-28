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
 * When the exact cut is not registered, resolution walks a ladder: the nearest
 * registered weight in the same slope, then the nearest in the opposite slope,
 * then — after the `arial` alias — the core font. Ties within a slope go to the
 * lighter cut. The core families only ship 400 and 700, so a request for 600
 * lands on the bold file.
 *
 * A `register()`ed font wins over the alias — registering an actual "Arial"
 * embeds that file rather than falling back to bundled Helvetica.
 */
final class FontRepository
{
    private const CORE_FAMILIES = ['courier', 'helvetica', 'times', 'symbol', 'zapfdingbats'];
    private const STYLELESS_FAMILIES = ['symbol', 'zapfdingbats'];

    /** Larger than any possible weight gap, so slope always outranks weight. */
    private const SLOPE_PENALTY = 1000;

    /** @var array<string, FontDefinition> resolution key => definition */
    private array $cache = [];

    /** @var array<string, FontDefinition> definition file path => definition */
    private array $loaded = [];

    /** @var array<string, list<array{face: FontFace, path: string}>> family => registered cuts */
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
        $key = self::familyKey($family);
        $cuts = array_values(array_filter(
            $this->registrations[$key] ?? [],
            static fn (array $cut): bool => !$cut['face']->equals($face),
        ));
        $cuts[] = ['face' => $face, 'path' => $definitionPath];
        $this->registrations[$key] = $cuts;

        // A new cut can change what a *neighbouring* weight resolves to, so the
        // whole cache goes rather than one key.
        $this->cache = [];
    }

    public function resolve(string $family, FontFace $face): FontDefinition
    {
        $family = self::familyKey($family);

        return $this->cache[$family . ':' . $face->key()] ??= $this->load($this->pathFor($family, $face));
    }

    private function pathFor(string $family, FontFace $face): string
    {
        // A registration for the family exactly as asked wins, before any aliasing.
        $registered = $this->registeredPath($family, $face);
        if ($registered !== null) {
            return $registered;
        }

        $aliased = $family === 'arial' ? 'helvetica' : $family;
        if (in_array($aliased, self::STYLELESS_FAMILIES, true)) {
            $face = FontFace::regular();
        }

        $path = $this->registeredPath($aliased, $face) ?? $this->corePath($aliased, $face);
        if ($path === null) {
            throw new FontException(sprintf('Undefined font: %s %s', $family, $face->describe()));
        }

        return $path;
    }

    /**
     * The registered cut closest to the requested one, or null if the family
     * has none.
     *
     * TODO: synthesise the missing cut rather than borrowing a neighbour —
     * FPDF-style faux bold (text render mode 2 + a hairline stroke) and faux
     * oblique (a sheared text matrix).
     */
    private function registeredPath(string $family, FontFace $face): ?string
    {
        $best = null;
        $bestDistance = PHP_INT_MAX;
        $bestWeight = PHP_INT_MAX;

        foreach ($this->registrations[$family] ?? [] as $cut) {
            $distance = abs($cut['face']->weight - $face->weight)
                + ($cut['face']->italic === $face->italic ? 0 : self::SLOPE_PENALTY);

            if ($distance < $bestDistance || ($distance === $bestDistance && $cut['face']->weight < $bestWeight)) {
                $best = $cut['path'];
                $bestDistance = $distance;
                $bestWeight = $cut['face']->weight;
            }
        }

        return $best;
    }

    private function corePath(string $family, FontFace $face): ?string
    {
        if (!in_array($family, self::CORE_FAMILIES, true)) {
            return null;
        }

        $suffix = FontStyle::of($face->isBold(), $face->italic)->fileSuffix();

        return sprintf('%s/%s%s.json', $this->fontDirectory, $family, $suffix);
    }

    private function load(string $path): FontDefinition
    {
        return $this->loaded[$path] ??= $this->loader->load($path);
    }

    private static function familyKey(string $family): string
    {
        return strtolower(trim($family));
    }
}
