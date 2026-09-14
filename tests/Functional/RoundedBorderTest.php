<?php

declare(strict_types=1);

namespace Pdf\Tests\Functional;

use Pdf\Color\Color;
use Pdf\Document;
use Pdf\Node\Paragraph;
use Pdf\Style\Border;
use Pdf\Style\StylePatch;
use Pdf\Tests\Support\Pdf;
use PHPUnit\Framework\TestCase;

final class RoundedBorderTest extends TestCase
{
    public function test_a_square_border_still_uses_the_straight_edge_operators(): void
    {
        $pdf = Document::create()->using(Pdf::deterministicRenderer())
            ->page(fn ($p) => $p->container(
                [new Paragraph('square')],
                new StylePatch(border: Border::uniform(1.0, Color::black())),
            ))
            ->toString();

        $text = Pdf::contentText($pdf);
        self::assertStringNotContainsString(' c' . "\n", $text);
    }

    public function test_a_rounded_border_draws_bezier_corners(): void
    {
        $pdf = Document::create()->using(Pdf::deterministicRenderer())
            ->page(fn ($p) => $p->container(
                [new Paragraph('rounded')],
                new StylePatch(border: Border::uniform(1.0, Color::black(), radiusPt: 6.0)),
            ))
            ->toString();

        $text = Pdf::contentText($pdf);
        self::assertSame(4, substr_count($text, ' c' . "\n"));
        self::assertStringContainsString("\nS\n", $text);
    }

    public function test_a_rounded_background_and_border_together_fill_and_stroke_one_path(): void
    {
        $pdf = Document::create()->using(Pdf::deterministicRenderer())
            ->page(fn ($p) => $p->container(
                [new Paragraph('card')],
                new StylePatch(
                    background: Color::rgb(255, 245, 150),
                    border: Border::uniform(0.5, Color::gray(180), radiusPt: 4.0),
                ),
            ))
            ->toString();

        $text = Pdf::contentText($pdf);
        self::assertStringContainsString("\nB\n", $text);
    }

    public function test_a_rounded_background_with_no_border_only_fills(): void
    {
        $pdf = Document::create()->using(Pdf::deterministicRenderer())
            ->page(fn ($p) => $p->container(
                [new Paragraph('filled')],
                new StylePatch(background: Color::rgb(200, 220, 255), border: Border::none()->withRadius(5.0)),
            ))
            ->toString();

        $text = Pdf::contentText($pdf);
        self::assertStringContainsString("\nf\n", $text);
        self::assertStringNotContainsString("\nS\n", $text);
    }

    public function test_zero_radius_stays_byte_identical_to_the_straight_edged_render(): void
    {
        $renderer = Pdf::deterministicRenderer();
        $patch = fn (float $radiusPt) => new StylePatch(
            background: Color::rgb(255, 245, 150),
            border: Border::uniform(0.5, Color::gray(180), $radiusPt),
        );

        $square = Document::create()->using($renderer)
            ->page(fn ($p) => $p->container([new Paragraph('card')], $patch(0.0)))
            ->toString();

        $plain = Document::create()->using($renderer)
            ->page(fn ($p) => $p->container(
                [new Paragraph('card')],
                new StylePatch(background: Color::rgb(255, 245, 150), border: Border::uniform(0.5, Color::gray(180))),
            ))
            ->toString();

        self::assertSame($plain, $square);
    }
}
