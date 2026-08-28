<?php

declare(strict_types=1);

namespace Pdf\Tests\Unit;

use Pdf\Font\FontFace;
use Pdf\Font\FontRepository;
use Pdf\Font\FontStyle;
use PHPUnit\Framework\TestCase;

final class FontFaceTest extends TestCase
{
    /**
     * Core definition files stand in for a brand family's cuts: each one has a
     * distinct `name`, so the assertions say which file resolution picked.
     */
    private function definition(string $file): string
    {
        return dirname(__DIR__, 2) . '/resources/fonts/' . $file;
    }

    public function test_legacy_styles_map_onto_the_400_700_pair(): void
    {
        self::assertTrue(FontStyle::Regular->face()->equals(new FontFace(400)));
        self::assertTrue(FontStyle::Bold->face()->equals(new FontFace(700)));
        self::assertTrue(FontStyle::Italic->face()->equals(new FontFace(400, true)));
        self::assertTrue(FontStyle::BoldItalic->face()->equals(new FontFace(700, true)));
    }

    public function test_semibold_counts_as_bold(): void
    {
        self::assertFalse((new FontFace(500))->isBold());
        self::assertTrue((new FontFace(600))->isBold());
        self::assertTrue((new FontFace(900))->isBold());
    }

    public function test_semibold_snaps_to_the_nearest_registered_weight(): void
    {
        $repo = FontRepository::withBundledFonts();
        $repo->register('Brand', new FontFace(400), $this->definition('helvetica.json'));
        $repo->register('Brand', new FontFace(700), $this->definition('times.json'));

        self::assertSame('Times-Roman', $repo->resolve('Brand', new FontFace(600))->name);
    }

    public function test_an_exactly_registered_semibold_wins_over_the_nearest(): void
    {
        $repo = FontRepository::withBundledFonts();
        $repo->register('Brand', new FontFace(400), $this->definition('helvetica.json'));
        $repo->register('Brand', new FontFace(600), $this->definition('courier.json'));
        $repo->register('Brand', new FontFace(700), $this->definition('times.json'));

        self::assertSame('Courier', $repo->resolve('Brand', new FontFace(600))->name);
    }

    public function test_every_cut_of_a_three_weight_family_resolves_to_its_own_file(): void
    {
        $repo = FontRepository::withBundledFonts();
        $repo->register('Brand', new FontFace(300), $this->definition('courier.json'));
        $repo->register('Brand', new FontFace(400), $this->definition('helvetica.json'));
        $repo->register('Brand', new FontFace(900), $this->definition('times.json'));

        self::assertSame('Courier', $repo->resolve('Brand', new FontFace(300))->name);
        self::assertSame('Helvetica', $repo->resolve('Brand', new FontFace(400))->name);
        self::assertSame('Times-Roman', $repo->resolve('Brand', new FontFace(900))->name);
        self::assertSame('Courier', $repo->resolve('Brand', new FontFace(100))->name);
        self::assertSame('Times-Roman', $repo->resolve('Brand', new FontFace(800))->name);
    }

    public function test_the_same_slope_outranks_a_nearer_weight_in_the_other_slope(): void
    {
        $repo = FontRepository::withBundledFonts();
        $repo->register('Brand', new FontFace(700), $this->definition('helvetica.json'));
        $repo->register('Brand', new FontFace(300, true), $this->definition('timesi.json'));

        self::assertSame('Times-Italic', $repo->resolve('Brand', new FontFace(700, true))->name);
    }

    public function test_the_opposite_slope_is_used_when_a_family_has_no_italic_cut(): void
    {
        $repo = FontRepository::withBundledFonts();
        $repo->register('Brand', new FontFace(400), $this->definition('helvetica.json'));

        self::assertSame('Helvetica', $repo->resolve('Brand', new FontFace(400, true))->name);
    }

    public function test_core_families_snap_a_semibold_request_to_the_bold_cut(): void
    {
        $repo = FontRepository::withBundledFonts();

        self::assertSame('Helvetica-Bold', $repo->resolve('Helvetica', new FontFace(600))->name);
        self::assertSame('Helvetica', $repo->resolve('Helvetica', new FontFace(500))->name);
        self::assertSame('Times-Italic', $repo->resolve('Times', new FontFace(300, true))->name);
    }

    public function test_the_legacy_font_style_path_still_resolves(): void
    {
        $repo = FontRepository::withBundledFonts();

        self::assertSame('Helvetica-Bold', $repo->resolve('Helvetica', FontStyle::Bold->face())->name);
        self::assertSame('Helvetica-Oblique', $repo->resolve('Helvetica', FontStyle::Italic->face())->name);
    }

    public function test_re_registering_a_cut_replaces_it(): void
    {
        $repo = FontRepository::withBundledFonts();
        $repo->register('Brand', new FontFace(400), $this->definition('helvetica.json'));
        self::assertSame('Helvetica', $repo->resolve('Brand', new FontFace(400))->name);

        $repo->register('Brand', new FontFace(400), $this->definition('courier.json'));
        self::assertSame('Courier', $repo->resolve('Brand', new FontFace(400))->name);
    }

    public function test_a_later_registration_displaces_an_already_resolved_neighbour(): void
    {
        $repo = FontRepository::withBundledFonts();
        $repo->register('Brand', new FontFace(400), $this->definition('helvetica.json'));
        self::assertSame('Helvetica', $repo->resolve('Brand', new FontFace(600))->name);

        $repo->register('Brand', new FontFace(600), $this->definition('courier.json'));
        self::assertSame('Courier', $repo->resolve('Brand', new FontFace(600))->name);
    }
}
