<?php

declare(strict_types=1);

namespace Pdf\Builder;

/**
 * The three built-in cover-page treatments offered by {@see CoverBuilder}.
 *
 *  - `Centered`   — title block centred vertically on the sheet
 *  - `TopLeft`    — logo and title ranged left near the top
 *  - `BottomBand` — title reversed out of a tinted band near the foot
 *
 * Each preset measures the cover's actual content box (which follows the
 * cover's own page size) and positions its block from that, so all three adapt
 * to any page size and orientation without spilling onto a second sheet.
 */
enum CoverLayout
{
    case Centered;
    case TopLeft;
    case BottomBand;
}
