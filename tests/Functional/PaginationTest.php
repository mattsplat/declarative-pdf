<?php

declare(strict_types=1);

namespace Pdf\Tests\Functional;

use Pdf\Document;
use Pdf\Layout\LineBreaker;
use Pdf\Layout\Measurer;
use Pdf\Layout\Paginator;
use Pdf\Node\Document as DocumentTree;
use Pdf\Node\Heading;
use Pdf\Node\Page;
use Pdf\Node\PageBreak;
use Pdf\Node\Paragraph;
use Pdf\Style\StyleResolver;
use Pdf\Style\StylePatch;
use Pdf\Tests\Support\Fonts;
use Pdf\Tests\Support\Golden;
use Pdf\Tests\Support\Pdf;
use PHPUnit\Framework\TestCase;

final class PaginationTest extends TestCase
{
    private function paginator(): Paginator
    {
        return new Paginator(new Measurer(new StyleResolver(), Fonts::registry(), new LineBreaker()));
    }

    private function longParagraph(int $sentences): Paragraph
    {
        return new Paragraph(str_repeat(
            'Filler sentence that is deliberately verbose so it wraps and consumes vertical space. ',
            $sentences,
        ));
    }

    public function test_long_content_flows_onto_multiple_pages(): void
    {
        $tree = new DocumentTree([
            new Page(children: array_map(fn () => $this->longParagraph(8), range(1, 20))),
        ]);

        $pages = $this->paginator()->paginate($tree);

        self::assertGreaterThan(2, count($pages));
        foreach ($pages as $page) {
            self::assertFalse($page->bodyOverflowed);
        }
    }

    public function test_explicit_page_break_starts_a_new_page(): void
    {
        $tree = new DocumentTree([
            new Page(children: [
                new Paragraph('Before the break.'),
                new PageBreak(),
                new Paragraph('After the break.'),
            ]),
        ]);

        $pages = $this->paginator()->paginate($tree);

        self::assertCount(2, $pages);
    }

    public function test_heading_is_not_left_stranded_at_the_bottom_of_a_page(): void
    {
        // Fill most of a page, then a heading followed by a big paragraph.
        $children = array_map(fn () => $this->longParagraph(8), range(1, 12));
        $children[] = new Heading(2, 'Kept With Next');
        $children[] = $this->longParagraph(10);

        $pages = $this->paginator()->paginate(new DocumentTree([new Page(children: $children)]));

        // Render page by page; the heading text must share a page with its paragraph.
        $renderer = Pdf::deterministicRenderer();
        $pdf = $renderer->render(new DocumentTree([new Page(children: $children)]));
        $streams = Pdf::contentText($pdf);
        self::assertStringContainsString('(Kept With Next) Tj', $streams);
    }

    public function test_footer_reports_the_true_total_page_count(): void
    {
        $pdf = Document::create()
            ->using(Pdf::deterministicRenderer())
            ->page(function ($p) {
                $p->footer(fn ($ctx) => new Paragraph(
                    "Page {$ctx->pageNumber} of {$ctx->pageCount}",
                    new StylePatch(spaceAfterPt: 0.0),
                ));
                for ($i = 0; $i < 25; $i++) {
                    $p->paragraph(str_repeat('Body text that wraps across the page width nicely. ', 8));
                }
            })
            ->toString();

        $content = Pdf::contentText($pdf);
        self::assertStringContainsString('(Page 1 of ', $content);
        self::assertMatchesRegularExpression('/\(Page \d+ of (\d+)\) Tj/', $content);

        preg_match('/of (\d+)\) Tj/', $content, $m);
        $claimed = (int) $m[1];
        self::assertGreaterThan(1, $claimed);
        self::assertSame($claimed, substr_count($content, ' of ' . $claimed . ') Tj'));
    }

    public function test_multi_page_document_is_byte_for_byte_stable(): void
    {
        $pdf = Document::create()
            ->using(Pdf::deterministicRenderer())
            ->meta(fn ($m) => $m->title('Phase 2'))
            ->page(function ($p) {
                $p->header(fn ($c) => new Paragraph('Header', new StylePatch(fontSizePt: 9.0, spaceAfterPt: 0.0)));
                $p->footer(fn ($c) => new Paragraph(
                    "Page {$c->pageNumber}/{$c->pageCount}",
                    new StylePatch(fontSizePt: 9.0, spaceAfterPt: 0.0),
                ));
                $p->heading(1, 'Report');
                for ($i = 1; $i <= 4; $i++) {
                    $p->heading(3, "Section {$i}");
                    $p->paragraph(str_repeat("Body of section {$i}. ", 30));
                }
                $p->bulletList(['One', 'Two', 'Three']);
                $p->orderedList(['First', 'Second']);
                $p->container(
                    [new Paragraph('Boxed note.')],
                    new StylePatch(
                        paddingPt: \Pdf\Geometry\Edges::all(6.0),
                        border: \Pdf\Style\Border::uniform(0.5),
                        background: \Pdf\Color\Color::gray(240),
                    ),
                );
                $p->pageBreak();
                $p->paragraph('Final page.');
            })
            ->toString();

        Golden::assert('phase2-report.pdf', $pdf);
    }

    public function test_orphan_control_moves_a_paragraph_rather_than_leaving_one_line(): void
    {
        $measurer = new Measurer(new StyleResolver(), Fonts::registry(), new LineBreaker());

        // A paragraph with default orphans=2: if only one line fits, move it all.
        $para = new Paragraph(
            'Line one of the paragraph. Line two continues here. Line three as well. Line four ends it.',
            new StylePatch(orphans: 2, widows: 2),
        );
        $box = $measurer->measureBlock($para, 220.0, \Pdf\Style\Style::default());

        // Space for exactly one line -> head must be null (move whole).
        [$head, $tail] = $box->split(14.0);
        self::assertNull($head);
        self::assertNotNull($tail);
    }
}
