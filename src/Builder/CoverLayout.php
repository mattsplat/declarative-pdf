<?php

declare(strict_types=1);

namespace Pdf\Builder;

/**
 * The three built-in cover-page treatments offered by {@see CoverBuilder}.
 *
 *  - `Centered`   — title block centred on the sheet
 *  - `TopLeft`    — logo and title ranged left near the top
 *  - `BottomBand` — title reversed out of a tinted band near the foot
 */
enum CoverLayout
{
    case Centered;
    case TopLeft;
    case BottomBand;
}
