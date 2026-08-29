<?php

declare(strict_types=1);

namespace Pdf\Tests\Unit;

use Pdf\Node\Bookmark;
use Pdf\Render\OutlineTree;
use PHPUnit\Framework\TestCase;

final class OutlineTreeTest extends TestCase
{
    public function test_empty_list_is_empty(): void
    {
        $tree = new OutlineTree([]);

        self::assertTrue($tree->isEmpty());
        self::assertSame([], $tree->roots);
    }

    public function test_flat_list_is_all_top_level_siblings(): void
    {
        $tree = new OutlineTree([
            new Bookmark('A', 'a'),
            new Bookmark('B', 'b'),
            new Bookmark('C', 'c'),
        ]);

        self::assertSame([0, 1, 2], $tree->roots);
        self::assertSame([-1, -1, -1], $tree->parents);
        self::assertSame([[], [], []], $tree->children);
        self::assertSame([0, 0, 0], $tree->counts);
        self::assertSame([0, 1, 2], $tree->siblings(1));
    }

    public function test_levels_nest_under_the_nearest_preceding_lower_level(): void
    {
        $tree = new OutlineTree([
            new Bookmark('Ch1', 'c1', 0),
            new Bookmark('Ch1.1', 'c1a', 1),
            new Bookmark('Ch1.2', 'c1b', 1),
            new Bookmark('Ch2', 'c2', 0),
            new Bookmark('Ch2.1', 'c2a', 1),
        ]);

        self::assertSame([0, 3], $tree->roots);
        self::assertSame([-1, 0, 0, -1, 3], $tree->parents);
        self::assertSame([[1, 2], [], [], [4], []], $tree->children);
        self::assertSame([2, 0, 0, 1, 0], $tree->counts);
        self::assertSame([1, 2], $tree->siblings(1));
        self::assertSame([0, 3], $tree->siblings(3));
    }

    public function test_deep_descendant_counts_roll_up_through_every_level(): void
    {
        $tree = new OutlineTree([
            new Bookmark('1', 'a', 0),
            new Bookmark('1.1', 'b', 1),
            new Bookmark('1.1.1', 'c', 2),
            new Bookmark('1.1.2', 'd', 2),
        ]);

        self::assertSame([-1, 0, 1, 1], $tree->parents);
        self::assertSame(3, $tree->counts[0]);
        self::assertSame(2, $tree->counts[1]);
    }

    public function test_a_level_that_skips_a_step_is_clamped_to_parent_plus_one(): void
    {
        $tree = new OutlineTree([
            new Bookmark('Top', 'a', 0),
            new Bookmark('Jumped', 'b', 5),
            new Bookmark('Back', 'c', 1),
        ]);

        // "Jumped" cannot dangle four levels below its parent — it is clamped to
        // a direct child of "Top". "Back" at level 1 is then its sibling.
        self::assertSame([-1, 0, 0], $tree->parents);
        self::assertSame([[1, 2], [], []], $tree->children);
    }
}
