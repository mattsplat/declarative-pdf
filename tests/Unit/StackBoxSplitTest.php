<?php

declare(strict_types=1);

namespace Pdf\Tests\Unit;

use Pdf\Layout\Box;
use Pdf\Layout\Box\PageBreakBox;
use Pdf\Layout\Box\StackBox;
use Pdf\Tests\Support\FakeBox;
use PHPUnit\Framework\TestCase;

final class StackBoxSplitTest extends TestCase
{
    /** @return list<string> */
    private static function labels(?StackBox $stack): array
    {
        if ($stack === null) {
            return [];
        }

        return array_map(
            static fn (Box $b) => $b instanceof FakeBox ? $b->label : $b::class,
            $stack->children(),
        );
    }

    public function test_places_boxes_that_fit_and_defers_the_rest(): void
    {
        $stack = new StackBox([
            new FakeBox('a', 40),
            new FakeBox('b', 40),
            new FakeBox('c', 40),
        ]);

        [$head, $tail] = $stack->split(100.0);

        self::assertSame(['a', 'b'], self::labels($head));
        self::assertSame(['c'], self::labels($tail));
    }

    public function test_splits_a_splittable_box_at_the_boundary(): void
    {
        $stack = new StackBox([
            new FakeBox('a', 30),
            new FakeBox('big', 100, splittable: true),
        ]);

        [$head, $tail] = $stack->split(70.0);

        self::assertSame(['a', 'big#head'], self::labels($head));
        self::assertSame(['big#tail'], self::labels($tail));
    }

    public function test_moves_an_unsplittable_box_whole(): void
    {
        $stack = new StackBox([
            new FakeBox('a', 30),
            new FakeBox('solid', 100, splittable: false),
        ]);

        [$head, $tail] = $stack->split(70.0);

        self::assertSame(['a'], self::labels($head));
        self::assertSame(['solid'], self::labels($tail));
    }

    public function test_keep_with_next_drags_the_heading_to_the_next_page(): void
    {
        $stack = new StackBox([
            new FakeBox('body', 40),
            new FakeBox('heading', 20, keepWithNext: true),
            new FakeBox('section', 60, splittable: false),
        ]);

        // Room for body + heading, but not the section that must follow the heading.
        [$head, $tail] = $stack->split(70.0);

        self::assertSame(['body'], self::labels($head));
        self::assertSame(['heading', 'section'], self::labels($tail));
    }

    public function test_page_break_box_ends_the_page(): void
    {
        $stack = new StackBox([
            new FakeBox('a', 20),
            new PageBreakBox(),
            new FakeBox('b', 20),
        ]);

        [$head, $tail] = $stack->split(1000.0);

        self::assertSame(['a'], self::labels($head));
        self::assertSame(['b'], self::labels($tail));
    }

    public function test_collapsing_margins_between_boxes(): void
    {
        $stack = new StackBox([
            new FakeBox('a', 10, marginAfter: 20),
            new FakeBox('b', 10, marginBefore: 8),
        ]);

        // gap is max(20, 8) = 20, so content height is 10 + 20 + 10.
        self::assertSame(40.0, $stack->contentHeightPt());
    }
}
