<?php

declare(strict_types=1);

namespace Pdf\Tests\Support;

use Pdf\Font\FontFace;
use Pdf\Font\FontRegistry;
use Pdf\Font\FontRepository;
use Pdf\Font\ResolvedFont;

final class Fonts
{
    public static function registry(): FontRegistry
    {
        return new FontRegistry(FontRepository::withBundledFonts());
    }

    public static function helvetica(FontFace $face = new FontFace()): ResolvedFont
    {
        return self::registry()->use('Helvetica', $face);
    }
}
