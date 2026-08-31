<?php

declare(strict_types=1);

namespace Pdf\Tests\Functional;

use Pdf\Builder\DocumentBuilder;
use Pdf\Document;
use Pdf\Node\PushDirection;
use Pdf\Node\Transition;
use Pdf\Node\WipeDirection;
use Pdf\Tests\Support\Golden;
use Pdf\Tests\Support\Pdf;
use PHPUnit\Framework\TestCase;

final class TransitionTest extends TestCase
{
    private function deck(): DocumentBuilder
    {
        return Document::create()->using(Pdf::deterministicRenderer())
            ->presentation(advanceSeconds: 3.0)
            ->page(fn ($p) => $p
                ->transition(Transition::fade(0.5))
                ->heading(1, 'Title slide')
                ->paragraph('First slide.'))
            ->page(fn ($p) => $p
                ->transition(Transition::wipe(WipeDirection::Leftward))
                ->autoAdvance(8.0)
                ->heading(1, 'Second slide')
                ->paragraph('Held for eight seconds.'))
            ->page(fn ($p) => $p
                ->transition(Transition::push(PushDirection::Downward, 0.75))
                ->heading(1, 'Third slide')
                ->paragraph('Last slide.'));
    }

    public function test_each_page_dict_carries_its_trans_dictionary(): void
    {
        $pdf = $this->deck()->toString();

        self::assertStringContainsString('/Trans <</Type /Trans /S /Fade /D 0.5>>', $pdf);
        self::assertStringContainsString('/Trans <</Type /Trans /S /Wipe /D 1 /Di 180>>', $pdf);
        self::assertStringContainsString('/Trans <</Type /Trans /S /Push /D 0.75 /Di 270>>', $pdf);
    }

    public function test_a_deck_with_15_era_styles_bumps_the_pdf_header(): void
    {
        self::assertStringStartsWith('%PDF-1.5', $this->deck()->toString());
    }

    public function test_presentation_sets_full_screen_and_single_page_in_the_catalog(): void
    {
        $pdf = $this->deck()->toString();

        self::assertMatchesRegularExpression(
            '#/Type /Catalog\n/Pages 1 0 R\n/PageMode /FullScreen\n/PageLayout /SinglePage#',
            $pdf,
        );
    }

    public function test_presentation_without_an_interval_emits_no_dur(): void
    {
        $pdf = Document::create()->using(Pdf::deterministicRenderer())
            ->presentation()
            ->page(fn ($p) => $p->paragraph('one'))
            ->page(fn ($p) => $p->paragraph('two'))
            ->toString();

        self::assertStringContainsString('/PageMode /FullScreen', $pdf);
        self::assertStringContainsString('/PageLayout /SinglePage', $pdf);
        self::assertStringNotContainsString('/Dur ', $pdf);
    }

    public function test_auto_advance_without_presentation_emits_dur_but_not_full_screen(): void
    {
        $pdf = Document::create()->using(Pdf::deterministicRenderer())
            ->page(fn ($p) => $p->autoAdvance(5.0)->paragraph('one'))
            ->toString();

        self::assertStringContainsString('/Dur 5', $pdf);
        self::assertStringNotContainsString('/PageMode', $pdf);
    }

    public function test_document_advance_applies_unless_a_page_overrides_it(): void
    {
        $pdf = $this->deck()->toString();

        // Slides 1 and 3 inherit /Dur 3; slide 2 set its own /Dur 8.
        self::assertSame(2, substr_count($pdf, '/Dur 3'));
        self::assertSame(1, substr_count($pdf, '/Dur 8'));
    }

    public function test_a_transition_replays_on_every_sheet_a_long_page_flows_across(): void
    {
        $pdf = Document::create()->using(Pdf::deterministicRenderer())
            ->page(fn ($p) => $p
                ->transition(Transition::dissolve())
                ->paragraph(str_repeat('Filler paragraph that pushes the page past one sheet. ', 120)))
            ->toString();

        self::assertGreaterThanOrEqual(2, substr_count($pdf, '/Trans <</Type /Trans /S /Dissolve /D 1>>'));
    }

    public function test_a_plain_document_has_no_transition_or_presentation_keys(): void
    {
        $pdf = Document::create()->using(Pdf::deterministicRenderer())
            ->page(fn ($p) => $p->paragraph('plain'))
            ->toString();

        self::assertStringNotContainsString('/Trans <<', $pdf);
        self::assertStringNotContainsString('/Dur ', $pdf);
        self::assertStringNotContainsString('/PageMode', $pdf);
        self::assertStringStartsWith('%PDF-1.3', $pdf);
    }

    public function test_transitions_golden(): void
    {
        Golden::assert('transitions.pdf', $this->deck()->toString());
    }
}
