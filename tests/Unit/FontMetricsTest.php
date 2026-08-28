<?php

declare(strict_types=1);

namespace Pdf\Tests\Unit;

use Pdf\Font\FontRepository;
use Pdf\Font\FontFace;
use PHPUnit\Framework\TestCase;

final class FontMetricsTest extends TestCase
{
    public function test_string_width_matches_known_helvetica_afm_values(): void
    {
        $metrics = FontRepository::withBundledFonts()
            ->resolve('Helvetica', FontFace::regular())
            ->metrics();

        // H=722, i=222, .=278 units/1000em -> 1222 * 12 / 1000
        self::assertEqualsWithDelta(14.664, $metrics->stringWidth('Hi.', 12.0), 1e-9);
        self::assertEqualsWithDelta(278 * 10.0 / 1000, $metrics->stringWidth(' ', 10.0), 1e-9);
        self::assertSame(0.0, $metrics->stringWidth('', 12.0));
    }

    public function test_arial_is_aliased_to_helvetica(): void
    {
        $repo = FontRepository::withBundledFonts();

        self::assertSame(
            $repo->resolve('Helvetica', FontFace::bold())->name,
            $repo->resolve('Arial', FontFace::bold())->name,
        );
    }

    public function test_symbol_ignores_requested_style(): void
    {
        $repo = FontRepository::withBundledFonts();

        self::assertSame(
            $repo->resolve('Symbol', FontFace::regular())->name,
            $repo->resolve('Symbol', FontFace::boldItalic())->name,
        );
    }
}
