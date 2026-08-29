<?php

declare(strict_types=1);

namespace Pdf\Tests\Functional;

use Pdf\Color\Color;
use Pdf\Document;
use Pdf\Geometry\PageSize;
use Pdf\Geometry\Point;
use Pdf\Geometry\ShrinkMode;
use Pdf\Geometry\Unit;
use Pdf\Node\Paragraph;
use Pdf\Node\Path;
use Pdf\Style\FillRule;
use Pdf\Style\GradientStop;
use Pdf\Style\LinearGradient;
use Pdf\Style\Paint;
use Pdf\Style\RadialGradient;
use Pdf\Style\StylePatch;
use Pdf\Tests\Support\Golden;
use Pdf\Tests\Support\Pdf;
use PHPUnit\Framework\TestCase;

final class GradientClipTest extends TestCase
{
    /** @return list<GradientStop> */
    private function sunset(): array
    {
        return [
            new GradientStop(0.0, Color::fromHex('#f9d976')),
            new GradientStop(0.55, Color::fromHex('#e96443')),
            new GradientStop(1.0, Color::fromHex('#6a1b4d')),
        ];
    }

    public function test_gradient_and_clip_document_matches_golden(): void
    {
        $star = [];
        for ($k = 0; $k < 5; $k++) {
            $angle = deg2rad($k * 144 - 90);
            $star[] = new Point(24.0 + 24.0 * cos($angle), 24.0 + 24.0 * sin($angle));
        }

        $pdf = Document::create()
            ->using(Pdf::deterministicRenderer())
            ->meta(fn ($m) => $m->title('Gradients and clipping'))
            ->page(function ($p) use ($star): void {
                $p->units(Unit::Mm);

                $p->heading(1, 'Gradients and clipping');
                $p->path(Path::rectangle(
                    120,
                    24,
                    Paint::gradient(LinearGradient::horizontal($this->sunset())),
                    patch: new StylePatch(spaceAfterPt: 8.0),
                ));
                $p->path(Path::ellipse(120, 24, Paint::gradient(RadialGradient::centered([
                    new GradientStop(0.0, Color::white()),
                    new GradientStop(1.0, Color::fromHex('#2f6fbf')),
                ]))));

                $p->spacer(8);
                $p->clip(
                    Path::polygon($star, Paint::filled(Color::black())),
                    [
                        new Paragraph('CLIP', new StylePatch(fontSizePt: 18.0, spaceAfterPt: 0.0)),
                        Path::rectangle(48, 40, Paint::gradient(LinearGradient::vertical($this->sunset()))),
                    ],
                    FillRule::EvenOdd,
                );

                $p->place(120, 200, 40, 40, [
                    Path::rectangle(40, 40, Paint::gradient(RadialGradient::centered($this->sunset())), Unit::Mm),
                ], shrink: ShrinkMode::None);
            })
            ->toString();

        Golden::assert('gradient-clip.pdf', $pdf);
    }

    public function test_gradient_fill_registers_one_shading_per_path(): void
    {
        $pdf = Document::create()
            ->using(Pdf::deterministicRenderer())
            ->page(fn ($p) => $p
                ->size(PageSize::a4())->units(Unit::Pt)
                ->path(Path::rectangle(120, 40, Paint::gradient(LinearGradient::horizontal([
                    new GradientStop(0.0, Color::black()),
                    new GradientStop(1.0, Color::white()),
                ])), Unit::Pt)))
            ->toString();

        self::assertStringContainsString('/Shading <<', $pdf);
        self::assertStringContainsString('/ShadingType 2', $pdf);

        $content = Pdf::contentText($pdf);
        self::assertStringContainsString("W n\n/Sh1 sh", $content);
    }

    public function test_a_clip_taller_than_the_remaining_space_moves_to_the_next_page(): void
    {
        $pdf = Document::create()
            ->using(Pdf::deterministicRenderer())
            ->page(fn ($p) => $p
                ->size(PageSize::a4())->units(Unit::Pt)->margin(0)
                ->paragraph('Above.', new StylePatch(fontSizePt: 12.0, spaceAfterPt: 0.0))
                ->clip(
                    Path::rectangle(200, 841.89, Paint::filled(Color::black()), Unit::Pt),
                    [new Paragraph('Inside the clip.', new StylePatch(fontSizePt: 12.0))],
                ))
            ->toString();

        self::assertStringContainsString("/Count 2\n", $pdf);

        $content = Pdf::contentText($pdf);
        self::assertStringContainsString('W n', $content);
        self::assertStringContainsString('(Inside the clip.)', $content);
    }
}
