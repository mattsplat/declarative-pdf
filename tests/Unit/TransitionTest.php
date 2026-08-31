<?php

declare(strict_types=1);

namespace Pdf\Tests\Unit;

use Pdf\Node\GlitterDirection;
use Pdf\Node\PushDirection;
use Pdf\Node\Transition;
use Pdf\Node\TransitionAxis;
use Pdf\Node\TransitionMotion;
use Pdf\Node\WipeDirection;
use PHPUnit\Framework\TestCase;

final class TransitionTest extends TestCase
{
    public function test_split_emits_style_duration_axis_and_motion(): void
    {
        self::assertSame(
            '<</Type /Trans /S /Split /D 1 /Dm /H /M /I>>',
            Transition::split()->dictionary(),
        );
        self::assertSame(
            '<</Type /Trans /S /Split /D 2 /Dm /V /M /O>>',
            Transition::split(TransitionAxis::Vertical, TransitionMotion::Out, 2.0)->dictionary(),
        );
    }

    public function test_blinds_emits_axis_but_not_motion_or_direction(): void
    {
        $dict = Transition::blinds(TransitionAxis::Vertical)->dictionary();

        self::assertSame('<</Type /Trans /S /Blinds /D 1 /Dm /V>>', $dict);
        self::assertStringNotContainsString('/M ', $dict);
        self::assertStringNotContainsString('/Di', $dict);
    }

    public function test_box_emits_motion_only(): void
    {
        $dict = Transition::box(TransitionMotion::Out)->dictionary();

        self::assertSame('<</Type /Trans /S /Box /D 1 /M /O>>', $dict);
        self::assertStringNotContainsString('/Dm', $dict);
        self::assertStringNotContainsString('/Di', $dict);
    }

    public function test_wipe_emits_direction_as_an_angle_and_admits_all_four(): void
    {
        self::assertSame(
            '<</Type /Trans /S /Wipe /D 1 /Di 180>>',
            Transition::wipe(WipeDirection::Leftward)->dictionary(),
        );
        self::assertSame(
            '<</Type /Trans /S /Wipe /D 1 /Di 90>>',
            Transition::wipe(WipeDirection::Upward)->dictionary(),
        );
    }

    public function test_dissolve_and_fade_emit_only_style_and_duration(): void
    {
        self::assertSame('<</Type /Trans /S /Dissolve /D 0.5>>', Transition::dissolve(0.5)->dictionary());
        self::assertSame('<</Type /Trans /S /Fade /D 1>>', Transition::fade()->dictionary());
    }

    public function test_glitter_supports_the_diagonal_direction(): void
    {
        self::assertSame(
            '<</Type /Trans /S /Glitter /D 1 /Di 315>>',
            Transition::glitter(GlitterDirection::Diagonal)->dictionary(),
        );
        self::assertSame(
            '<</Type /Trans /S /Glitter /D 1 /Di 270>>',
            Transition::glitter(GlitterDirection::Downward)->dictionary(),
        );
    }

    public function test_push_cover_and_uncover_emit_only_the_axial_directions(): void
    {
        self::assertSame('<</Type /Trans /S /Push /D 1 /Di 0>>', Transition::push()->dictionary());
        self::assertSame(
            '<</Type /Trans /S /Cover /D 1 /Di 270>>',
            Transition::cover(PushDirection::Downward)->dictionary(),
        );
        self::assertSame(
            '<</Type /Trans /S /Uncover /D 1 /Di 0>>',
            Transition::uncover(PushDirection::Rightward)->dictionary(),
        );
    }

    public function test_fly_emits_direction_and_motion(): void
    {
        self::assertSame(
            '<</Type /Trans /S /Fly /D 1 /M /I /Di 0>>',
            Transition::fly()->dictionary(),
        );
        self::assertSame(
            '<</Type /Trans /S /Fly /D 0.25 /M /O /Di 270>>',
            Transition::fly(PushDirection::Downward, TransitionMotion::Out, 0.25)->dictionary(),
        );
    }

    public function test_duration_is_formatted_deterministically(): void
    {
        self::assertSame('<</Type /Trans /S /Fade /D 1>>', Transition::fade(1.0)->dictionary());
        self::assertSame('<</Type /Trans /S /Fade /D 3>>', Transition::fade(3.0)->dictionary());
        self::assertSame('<</Type /Trans /S /Fade /D 1.5>>', Transition::fade(1.5)->dictionary());
        self::assertSame('<</Type /Trans /S /Fade /D 0>>', Transition::fade(0.0)->dictionary());
    }

    public function test_requires_pdf_15_only_for_15_era_constructs(): void
    {
        self::assertFalse(Transition::blinds()->requiresPdf15());
        self::assertFalse(Transition::dissolve()->requiresPdf15());

        self::assertTrue(Transition::split()->requiresPdf15());   // /M
        self::assertTrue(Transition::box()->requiresPdf15());     // /M
        self::assertTrue(Transition::wipe()->requiresPdf15());    // /Di
        self::assertTrue(Transition::glitter()->requiresPdf15()); // /Di
        self::assertTrue(Transition::fade()->requiresPdf15());    // 1.5 style
        self::assertTrue(Transition::push()->requiresPdf15());
        self::assertTrue(Transition::cover()->requiresPdf15());
        self::assertTrue(Transition::uncover()->requiresPdf15());
        self::assertTrue(Transition::fly()->requiresPdf15());
    }
}
