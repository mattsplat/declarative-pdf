<?php

declare(strict_types=1);

namespace Pdf\Exception;

/**
 * Raised when an SVG cannot be parsed, or uses a feature outside the supported
 * subset. {@see \Pdf\Svg\SvgParser} documents which elements are skipped and
 * which are refused.
 */
final class SvgException extends PdfException
{
}
