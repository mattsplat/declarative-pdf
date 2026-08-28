<?php

declare(strict_types=1);

namespace Pdf\Tests\Unit;

use Pdf\Exception\FontException;
use Pdf\Font\FontFace;
use Pdf\Font\FontRegistry;
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

    public function test_overriding_one_cut_of_a_core_family_leaves_the_others_bundled(): void
    {
        $repo = FontRepository::withBundledFonts();
        $repo->register('Helvetica', new FontFace(400), $this->definition('courier.json'));

        self::assertSame('Courier', $repo->resolve('Helvetica', new FontFace(400))->name);
        self::assertSame('Helvetica-Bold', $repo->resolve('Helvetica', FontFace::bold())->name);
        self::assertSame('Helvetica-Oblique', $repo->resolve('Helvetica', FontFace::italic())->name);
    }

    public function test_a_non_core_family_snaps_a_bold_request_onto_its_only_cut(): void
    {
        $repo = FontRepository::withBundledFonts();
        $repo->register('Brand', new FontFace(400), $this->definition('helvetica.json'));

        self::assertSame('Helvetica', $repo->resolve('Brand', FontFace::bold())->name);
    }

    public function test_a_family_with_no_cut_and_no_core_fallback_throws(): void
    {
        $this->expectException(FontException::class);
        $this->expectExceptionMessage('Undefined font: brand 700');

        FontRepository::withBundledFonts()->resolve('Brand', FontFace::bold());
    }

    public function test_weights_outside_the_css_range_are_rejected(): void
    {
        self::assertSame(1, (new FontFace(FontFace::MIN_WEIGHT))->weight);
        self::assertSame(1000, (new FontFace(FontFace::MAX_WEIGHT))->weight);

        $this->expectException(FontException::class);
        new FontFace(2000);
    }

    public function test_a_zero_weight_is_rejected(): void
    {
        $this->expectException(FontException::class);
        new FontFace(0);
    }

    public function test_two_weights_resolving_to_one_file_share_a_font_resource(): void
    {
        $repo = FontRepository::withBundledFonts();
        $repo->register('Brand', new FontFace(400), $this->definition('helvetica.json'));
        $registry = new FontRegistry($repo);

        $regular = $registry->use('Brand', new FontFace(400));
        $medium = $registry->use('Brand', new FontFace(500));

        self::assertSame($regular, $medium);
        self::assertCount(1, $registry->used());
    }

    public function test_distinct_files_still_get_distinct_font_resources(): void
    {
        $registry = new FontRegistry(FontRepository::withBundledFonts());
        $registry->use('Helvetica', FontFace::regular());
        $registry->use('Helvetica', FontFace::bold());

        self::assertCount(2, $registry->used());
        self::assertSame([1, 2], array_map(static fn ($font): int => $font->index, $registry->used()));
    }
}
