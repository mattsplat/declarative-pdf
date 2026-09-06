<?php

declare(strict_types=1);

namespace Pdf\Svg;

use Pdf\Color\Color;
use Pdf\Exception\SvgException;

/**
 * CSS colour syntax as it appears in SVG paint attributes.
 *
 * {@see Color::fromHex()} already covers `#rgb` / `#rrggbb`; what SVG adds is
 * the named-colour vocabulary, the `rgb()` / `rgba()` functions, the `none`
 * and `currentColor` keywords, and an optional alpha channel. The named table
 * lives here rather than on {@see Color} — it is SVG's vocabulary, not the
 * library's.
 *
 * Alpha is read separately by {@see self::alpha()} because a PDF expresses it
 * as graphics state (`/ca`, `/CA`), not as part of the colour operand.
 */
final class SvgColor
{
    /** @var array<string, string> CSS Color Module Level 4 named colours. */
    private const NAMED = [
        'aliceblue' => 'f0f8ff', 'antiquewhite' => 'faebd7', 'aqua' => '00ffff',
        'aquamarine' => '7fffd4', 'azure' => 'f0ffff', 'beige' => 'f5f5dc',
        'bisque' => 'ffe4c4', 'black' => '000000', 'blanchedalmond' => 'ffebcd',
        'blue' => '0000ff', 'blueviolet' => '8a2be2', 'brown' => 'a52a2a',
        'burlywood' => 'deb887', 'cadetblue' => '5f9ea0', 'chartreuse' => '7fff00',
        'chocolate' => 'd2691e', 'coral' => 'ff7f50', 'cornflowerblue' => '6495ed',
        'cornsilk' => 'fff8dc', 'crimson' => 'dc143c', 'cyan' => '00ffff',
        'darkblue' => '00008b', 'darkcyan' => '008b8b', 'darkgoldenrod' => 'b8860b',
        'darkgray' => 'a9a9a9', 'darkgreen' => '006400', 'darkgrey' => 'a9a9a9',
        'darkkhaki' => 'bdb76b', 'darkmagenta' => '8b008b', 'darkolivegreen' => '556b2f',
        'darkorange' => 'ff8c00', 'darkorchid' => '9932cc', 'darkred' => '8b0000',
        'darksalmon' => 'e9967a', 'darkseagreen' => '8fbc8f', 'darkslateblue' => '483d8b',
        'darkslategray' => '2f4f4f', 'darkslategrey' => '2f4f4f', 'darkturquoise' => '00ced1',
        'darkviolet' => '9400d3', 'deeppink' => 'ff1493', 'deepskyblue' => '00bfff',
        'dimgray' => '696969', 'dimgrey' => '696969', 'dodgerblue' => '1e90ff',
        'firebrick' => 'b22222', 'floralwhite' => 'fffaf0', 'forestgreen' => '228b22',
        'fuchsia' => 'ff00ff', 'gainsboro' => 'dcdcdc', 'ghostwhite' => 'f8f8ff',
        'gold' => 'ffd700', 'goldenrod' => 'daa520', 'gray' => '808080',
        'green' => '008000', 'greenyellow' => 'adff2f', 'grey' => '808080',
        'honeydew' => 'f0fff0', 'hotpink' => 'ff69b4', 'indianred' => 'cd5c5c',
        'indigo' => '4b0082', 'ivory' => 'fffff0', 'khaki' => 'f0e68c',
        'lavender' => 'e6e6fa', 'lavenderblush' => 'fff0f5', 'lawngreen' => '7cfc00',
        'lemonchiffon' => 'fffacd', 'lightblue' => 'add8e6', 'lightcoral' => 'f08080',
        'lightcyan' => 'e0ffff', 'lightgoldenrodyellow' => 'fafad2', 'lightgray' => 'd3d3d3',
        'lightgreen' => '90ee90', 'lightgrey' => 'd3d3d3', 'lightpink' => 'ffb6c1',
        'lightsalmon' => 'ffa07a', 'lightseagreen' => '20b2aa', 'lightskyblue' => '87cefa',
        'lightslategray' => '778899', 'lightslategrey' => '778899', 'lightsteelblue' => 'b0c4de',
        'lightyellow' => 'ffffe0', 'lime' => '00ff00', 'limegreen' => '32cd32',
        'linen' => 'faf0e6', 'magenta' => 'ff00ff', 'maroon' => '800000',
        'mediumaquamarine' => '66cdaa', 'mediumblue' => '0000cd', 'mediumorchid' => 'ba55d3',
        'mediumpurple' => '9370db', 'mediumseagreen' => '3cb371', 'mediumslateblue' => '7b68ee',
        'mediumspringgreen' => '00fa9a', 'mediumturquoise' => '48d1cc', 'mediumvioletred' => 'c71585',
        'midnightblue' => '191970', 'mintcream' => 'f5fffa', 'mistyrose' => 'ffe4e1',
        'moccasin' => 'ffe4b5', 'navajowhite' => 'ffdead', 'navy' => '000080',
        'oldlace' => 'fdf5e6', 'olive' => '808000', 'olivedrab' => '6b8e23',
        'orange' => 'ffa500', 'orangered' => 'ff4500', 'orchid' => 'da70d6',
        'palegoldenrod' => 'eee8aa', 'palegreen' => '98fb98', 'paleturquoise' => 'afeeee',
        'palevioletred' => 'db7093', 'papayawhip' => 'ffefd5', 'peachpuff' => 'ffdab9',
        'peru' => 'cd853f', 'pink' => 'ffc0cb', 'plum' => 'dda0dd',
        'powderblue' => 'b0e0e6', 'purple' => '800080', 'rebeccapurple' => '663399',
        'red' => 'ff0000', 'rosybrown' => 'bc8f8f', 'royalblue' => '4169e1',
        'saddlebrown' => '8b4513', 'salmon' => 'fa8072', 'sandybrown' => 'f4a460',
        'seagreen' => '2e8b57', 'seashell' => 'fff5ee', 'sienna' => 'a0522d',
        'silver' => 'c0c0c0', 'skyblue' => '87ceeb', 'slateblue' => '6a5acd',
        'slategray' => '708090', 'slategrey' => '708090', 'snow' => 'fffafa',
        'springgreen' => '00ff7f', 'steelblue' => '4682b4', 'tan' => 'd2b48c',
        'teal' => '008080', 'thistle' => 'd8bfd8', 'tomato' => 'ff6347',
        'turquoise' => '40e0d0', 'violet' => 'ee82ee', 'wheat' => 'f5deb3',
        'white' => 'ffffff', 'whitesmoke' => 'f5f5f5', 'yellow' => 'ffff00',
        'yellowgreen' => '9acd32',
    ];

    /**
     * The colour a paint attribute names, or null when it paints nothing
     * (`none` / `transparent`). `currentColor` resolves to `$currentColor`.
     */
    public static function parse(string $value, ?Color $currentColor = null): ?Color
    {
        $value = trim($value);
        $lower = strtolower($value);

        if ($lower === 'none' || $lower === 'transparent') {
            return null;
        }

        if ($lower === 'currentcolor') {
            return $currentColor ?? Color::black();
        }

        if (isset(self::NAMED[$lower])) {
            return Color::fromHex(self::NAMED[$lower]);
        }

        if (str_starts_with($value, '#')) {
            // 4- and 8-digit forms carry alpha, which self::alpha() reads; the
            // colour is the leading RGB half.
            $hex = substr($value, 1);
            $hex = match (strlen($hex)) {
                4 => substr($hex, 0, 3),
                8 => substr($hex, 0, 6),
                default => $hex,
            };

            return Color::fromHex($hex);
        }

        if (preg_match('/^rgba?\s*\((.*)\)$/i', $value, $match) === 1) {
            $parts = self::functionArguments($match[1]);
            if (count($parts) < 3) {
                throw new SvgException('Invalid rgb() colour: ' . $value);
            }

            return Color::rgb(
                self::component($parts[0]),
                self::component($parts[1]),
                self::component($parts[2]),
            );
        }

        throw new SvgException('Unsupported SVG colour: ' . $value);
    }

    /**
     * The alpha a colour value carries in itself — `#rgba`, `#rrggbbaa` or the
     * fourth `rgba()` argument. 1.0 for every other form. This multiplies with
     * `fill-opacity` / `stroke-opacity`, exactly as CSS composes them.
     */
    public static function alpha(string $value): float
    {
        $value = trim($value);

        if (str_starts_with($value, '#')) {
            $hex = substr($value, 1);

            return match (strlen($hex)) {
                4 => self::clamp((float) hexdec($hex[3] . $hex[3]) / 255.0),
                8 => self::clamp((float) hexdec(substr($hex, 6, 2)) / 255.0),
                default => 1.0,
            };
        }

        // Alpha may ride in either function: rgba(r,g,b,a) or rgb(r g b / a).
        if (preg_match('/^rgba?\s*\((.*)\)$/i', $value, $match) === 1) {
            $parts = self::functionArguments($match[1]);
            if (count($parts) === 4) {
                return self::clamp(self::ratio($parts[3]));
            }
        }

        return 1.0;
    }

    /**
     * An opacity attribute (`opacity`, `fill-opacity`, `stroke-opacity`): a
     * number 0-1, or a percentage.
     */
    public static function opacity(string $value): float
    {
        return self::clamp(self::ratio(trim($value)));
    }

    /** @return list<string> */
    private static function functionArguments(string $inside): array
    {
        // CSS Color 4 allows the space/slash form `rgb(0 128 255 / 50%)`.
        $parts = preg_split('#[\s,/]+#', trim($inside), -1, PREG_SPLIT_NO_EMPTY);

        return $parts === false ? [] : $parts;
    }

    /** One 0-255 colour channel, given either as a number or a percentage. */
    private static function component(string $token): int
    {
        $scale = str_ends_with($token, '%') ? 255.0 : 1.0;
        $value = self::number($token) * $scale;

        return (int) round(max(0.0, min(255.0, $value)));
    }

    /** A 0-1 ratio, given either as a number or a percentage. */
    private static function ratio(string $token): float
    {
        return self::number($token);
    }

    private static function number(string $token): float
    {
        if (str_ends_with($token, '%')) {
            $body = substr($token, 0, -1);
            if (!is_numeric($body)) {
                throw new SvgException('Invalid percentage: ' . $token);
            }

            return (float) $body / 100.0;
        }

        if (!is_numeric($token)) {
            throw new SvgException('Invalid number: ' . $token);
        }

        return (float) $token;
    }

    private static function clamp(float $value): float
    {
        return max(0.0, min(1.0, $value));
    }
}
