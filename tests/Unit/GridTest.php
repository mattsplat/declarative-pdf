<?php

declare(strict_types=1);

namespace Pdf\Tests\Unit;

use Pdf\Builder\PageBuilder;
use Pdf\Exception\PdfException;
use Pdf\Geometry\Orientation;
use Pdf\Geometry\PageSize;
use Pdf\Geometry\Rect;
use Pdf\Geometry\Unit;
use Pdf\Layout\Grid;
use Pdf\Layout\Track;
use PHPUnit\Framework\TestCase;

final class GridTest extends TestCase
{
    public function test_rows_split_by_normalised_weight(): void
    {
        [$a, $b, $c] = Grid::inside(new Rect(10.0, 20.0, 300.0, 400.0))->rows(1, 2, 1);

        self::assertSame(20.0, $a->rect()->y);
        self::assertEqualsWithDelta(100.0, $a->rect()->height, 1e-9);
        self::assertEqualsWithDelta(120.0, $b->rect()->y, 1e-9);
        self::assertEqualsWithDelta(200.0, $b->rect()->height, 1e-9);
        self::assertEqualsWithDelta(320.0, $c->rect()->y, 1e-9);
        self::assertEqualsWithDelta(100.0, $c->rect()->height, 1e-9);

        // Columns are unchanged by a row split.
        self::assertSame(10.0, $b->rect()->x);
        self::assertSame(300.0, $b->rect()->width);
    }

    public function test_columns_split_by_normalised_weight(): void
    {
        [$left, $right] = Grid::inside(new Rect(0.0, 0.0, 200.0, 100.0))->columns(3, 1);

        self::assertEqualsWithDelta(150.0, $left->rect()->width, 1e-9);
        self::assertEqualsWithDelta(150.0, $right->rect()->x, 1e-9);
        self::assertEqualsWithDelta(50.0, $right->rect()->width, 1e-9);
        self::assertSame(100.0, $left->rect()->height);
    }

    public function test_gutter_is_subtracted_between_slices(): void
    {
        [$a, $b, $c] = Grid::inside(new Rect(0.0, 0.0, 320.0, 100.0), gutterPt: 10.0)->columns(1, 1, 1);

        // 320 - 2 gutters = 300 shared -> 100 each, offset by width + gutter.
        self::assertEqualsWithDelta(100.0, $a->rect()->width, 1e-9);
        self::assertEqualsWithDelta(110.0, $b->rect()->x, 1e-9);
        self::assertEqualsWithDelta(220.0, $c->rect()->x, 1e-9);
        self::assertEqualsWithDelta(320.0, $c->rect()->x + $c->rect()->width, 1e-9);
    }

    public function test_nested_split_carries_the_gutter(): void
    {
        [$top, $bottom] = Grid::inside(new Rect(0.0, 0.0, 210.0, 300.0), gutterPt: 10.0)->rows(1, 1);
        [$left, $right] = $bottom->columns(1, 1);

        // rows(1, 1) of a 300pt height with a 10pt gutter -> 145pt bands; the
        // second starts at 145 + 10.
        self::assertEqualsWithDelta(155.0, $bottom->rect()->y, 1e-9);
        self::assertEqualsWithDelta(145.0, $bottom->rect()->height, 1e-9);
        // 210 - 10 gutter = 200 -> 100 each.
        self::assertEqualsWithDelta(100.0, $left->rect()->width, 1e-9);
        self::assertEqualsWithDelta(110.0, $right->rect()->x, 1e-9);
        self::assertEqualsWithDelta(155.0, $right->rect()->y, 1e-9);
    }

    public function test_gutter_override_replaces_the_carried_value(): void
    {
        [, $bottom] = Grid::inside(new Rect(0.0, 0.0, 200.0, 200.0), gutterPt: 20.0)->rows(1, 1);
        [$left, $right] = $bottom->gutter(0.0)->columns(1, 1);

        self::assertEqualsWithDelta(100.0, $left->rect()->width, 1e-9);
        self::assertEqualsWithDelta(100.0, $right->rect()->x, 1e-9);
    }

    public function test_fixed_and_fractional_tracks_combine(): void
    {
        [$header, $body, $footer] = Grid::inside(new Rect(0.0, 0.0, 100.0, 500.0))
            ->rowTracks([Track::pt(80.0), Track::fr(1), Track::pt(20.0)]);

        self::assertEqualsWithDelta(80.0, $header->rect()->height, 1e-9);
        self::assertEqualsWithDelta(400.0, $body->rect()->height, 1e-9);
        self::assertEqualsWithDelta(80.0, $body->rect()->y, 1e-9);
        self::assertEqualsWithDelta(20.0, $footer->rect()->height, 1e-9);
        self::assertEqualsWithDelta(480.0, $footer->rect()->y, 1e-9);
    }

    public function test_fixed_tracks_share_the_gutter_budget(): void
    {
        [$sidebar, $main] = Grid::inside(new Rect(0.0, 0.0, 330.0, 100.0), gutterPt: 30.0)
            ->columnTracks([Track::pt(100.0), Track::fr(1)]);

        self::assertEqualsWithDelta(100.0, $sidebar->rect()->width, 1e-9);
        // 330 - 30 gutter - 100 fixed = 200 for the single fr track.
        self::assertEqualsWithDelta(200.0, $main->rect()->width, 1e-9);
        self::assertEqualsWithDelta(130.0, $main->rect()->x, 1e-9);
    }

    public function test_fractional_tracks_may_be_absent(): void
    {
        [$a, $b] = Grid::inside(new Rect(0.0, 0.0, 100.0, 100.0))
            ->columnTracks([Track::pt(30.0), Track::pt(20.0)]);

        self::assertEqualsWithDelta(30.0, $a->rect()->width, 1e-9);
        self::assertEqualsWithDelta(30.0, $b->rect()->x, 1e-9);
        self::assertEqualsWithDelta(20.0, $b->rect()->width, 1e-9);
    }

    public function test_for_page_uses_the_writable_rect_in_points(): void
    {
        $page = (new PageBuilder())
            ->size(PageSize::a4())
            ->orientation(Orientation::Landscape)
            ->margin(10.0, Unit::Mm);

        $rect = Grid::forPage($page)->rect();
        $marginPt = Unit::Mm->toPoints(10.0);

        self::assertEqualsWithDelta($marginPt, $rect->x, 1e-9);
        self::assertEqualsWithDelta($marginPt, $rect->y, 1e-9);
        // A4 landscape: 841.89 x 595.28, less both margins.
        self::assertEqualsWithDelta(841.89 - $marginPt * 2, $rect->width, 1e-6);
        self::assertEqualsWithDelta(595.28 - $marginPt * 2, $rect->height, 1e-6);
    }

    public function test_overfull_fixed_tracks_are_rejected(): void
    {
        $this->expectException(PdfException::class);

        Grid::inside(new Rect(0.0, 0.0, 100.0, 100.0))
            ->columnTracks([Track::pt(80.0), Track::pt(80.0)]);
    }

    public function test_empty_split_is_rejected(): void
    {
        $this->expectException(PdfException::class);

        Grid::inside(new Rect(0.0, 0.0, 100.0, 100.0))->rowTracks([]);
    }

    public function test_negative_track_size_is_rejected(): void
    {
        $this->expectException(PdfException::class);

        Track::pt(-1.0);
    }
}
