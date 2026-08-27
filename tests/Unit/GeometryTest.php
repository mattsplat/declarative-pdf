<?php

declare(strict_types=1);

namespace Pdf\Tests\Unit;

use Pdf\Geometry\Edges;
use Pdf\Geometry\Orientation;
use Pdf\Geometry\PageGeometry;
use Pdf\Geometry\PageSize;
use Pdf\Geometry\Unit;
use PHPUnit\Framework\TestCase;

final class GeometryTest extends TestCase
{
    public function test_unit_scale_factors_match_fpdf(): void
    {
        self::assertSame(1.0, Unit::Pt->pointsPerUnit());
        self::assertEqualsWithDelta(72 / 25.4, Unit::Mm->pointsPerUnit(), 1e-12);
        self::assertEqualsWithDelta(72 / 2.54, Unit::Cm->pointsPerUnit(), 1e-12);
        self::assertSame(72.0, Unit::In->pointsPerUnit());
    }

    public function test_named_a4_matches_fpdf_std_page_sizes(): void
    {
        $a4 = PageSize::named('a4');

        self::assertEqualsWithDelta(595.28, $a4->widthPt, 1e-9);
        self::assertEqualsWithDelta(841.89, $a4->heightPt, 1e-9);
    }

    public function test_landscape_swaps_width_and_height(): void
    {
        $portrait = PageSize::a4()->forOrientation(Orientation::Portrait);
        $landscape = PageSize::a4()->forOrientation(Orientation::Landscape);

        self::assertGreaterThan($portrait->widthPt, $portrait->heightPt);
        self::assertGreaterThan($landscape->heightPt, $landscape->widthPt);
        self::assertEqualsWithDelta($portrait->widthPt, $landscape->heightPt, 1e-9);
    }

    public function test_content_box_and_y_flip(): void
    {
        $geometry = new PageGeometry(
            PageSize::a4(),
            Orientation::Portrait,
            Edges::all(20.0),
        );

        $box = $geometry->contentBox();
        self::assertSame(20.0, $box->x);
        self::assertSame(20.0, $box->y);
        self::assertEqualsWithDelta(595.28 - 40.0, $box->width, 1e-9);

        // A point 100pt below the top edge is (pageHeight - 100) from the bottom.
        self::assertEqualsWithDelta(841.89 - 100.0, $geometry->flipY(100.0), 1e-9);
    }
}
