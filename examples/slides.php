<?php

declare(strict_types=1);

require __DIR__ . '/../vendor/autoload.php';

use Pdf\Color\Color;
use Pdf\Document;
use Pdf\Geometry\Orientation;
use Pdf\Node\PushDirection;
use Pdf\Node\Transition;
use Pdf\Node\TransitionAxis;
use Pdf\Node\WipeDirection;
use Pdf\Style\StylePatch;
use Pdf\Style\TextAlign;

/*
 * A short slide deck.
 *
 *   ->presentation(advanceSeconds: 4)   opens full-screen, auto-advancing
 *                                       every 4 s
 *   ->transition(...)                   the effect played when each slide
 *                                       appears — see the Transition factories;
 *                                       the direction argument is style-scoped
 *                                       (WipeDirection / GlitterDirection /
 *                                       PushDirection) so only spec-valid
 *                                       angles compile
 *   ->autoAdvance(seconds)              a per-slide dwell time overriding the
 *                                       document default (slide 3 lingers)
 */

$ink = Color::rgb(24, 28, 40);
$accent = Color::rgb(70, 120, 210);

$title = new StylePatch(fontSizePt: 30.0, color: $ink, align: TextAlign::Center, spaceBeforePt: 120.0);
$lede = new StylePatch(fontSizePt: 14.0, color: Color::gray(90), align: TextAlign::Center);
$bullet = new StylePatch(fontSizePt: 16.0, color: $ink, spaceBeforePt: 8.0);

Document::create()
    ->meta(fn ($m) => $m->title('Declarative PDF — a deck')->author('declarative-pdf'))
    ->presentation(advanceSeconds: 4.0)
    ->page(function ($p) use ($title, $lede): void {
        $p->orientation(Orientation::Landscape)
            ->transition(Transition::fade(0.6))
            ->paragraph('Page transitions', $title)
            ->paragraph('and a one-command presentation mode', $lede);
    })
    ->page(function ($p) use ($accent, $bullet): void {
        $p->orientation(Orientation::Landscape)
            ->transition(Transition::wipe(WipeDirection::Leftward, 0.4))
            ->heading(2, 'What you get', new StylePatch(color: $accent))
            ->bulletList([
                'Eleven /Trans styles, each with only the keys it uses',
                'Per-page effect and dwell time',
                'presentation() flips the catalog to /FullScreen',
            ], patch: $bullet);
    })
    ->page(function ($p) use ($accent, $bullet): void {
        $p->orientation(Orientation::Landscape)
            ->transition(Transition::split(TransitionAxis::Vertical))
            ->autoAdvance(8.0)
            ->heading(2, 'This slide lingers', new StylePatch(color: $accent))
            ->paragraph('It set ->autoAdvance(8.0), overriding the 4-second document default.', $bullet);
    })
    ->page(function ($p) use ($title): void {
        $p->orientation(Orientation::Landscape)
            ->transition(Transition::push(PushDirection::Downward, 0.5))
            ->paragraph('Thanks', $title);
    })
    ->save(__DIR__ . '/slides.pdf');

echo 'Wrote ' . __DIR__ . "/slides.pdf\n";
