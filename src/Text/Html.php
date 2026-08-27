<?php

declare(strict_types=1);

namespace Pdf\Text;

use Pdf\Color\Color;
use Pdf\Style\StylePatch;

/**
 * A small HTML-to-{@see InlineSequence} converter for inline markup.
 *
 * The declarative successor to tuto6's `WriteHTML()` (tutorial/tuto6.php:11):
 * opt-in, inline only. Supported tags: `b`/`strong`, `i`/`em`, `u`,
 * `s`/`strike`/`del`, `sup`, `sub`, `a href="…"`, `br`. Any other `<…>` span is
 * emitted verbatim as text (so `Map<String,Int>` survives), and unknown
 * attributes on known tags are ignored. Whitespace is collapsed and entities
 * are decoded; a literal `<` next to a single letter (e.g. `a < b`) is
 * ambiguous with a tag — write `&lt;` for those.
 */
final class Html
{
    private const KNOWN_TAGS = ['b', 'strong', 'i', 'em', 'u', 's', 'strike', 'del', 'sup', 'sub', 'a', 'br'];

    /** @var array<string, int> */
    private const COUNTER_FOR = [
        'b' => 0, 'strong' => 0, 'i' => 1, 'em' => 1, 'u' => 2,
        's' => 3, 'strike' => 3, 'del' => 3, 'sup' => 4, 'sub' => 5,
    ];

    public static function toInline(string $html): InlineSequence
    {
        $sequence = InlineSequence::empty();
        $parts = preg_split('/<([^>]*)>/', $html, -1, PREG_SPLIT_DELIM_CAPTURE) ?: [$html];

        $counters = [0, 0, 0, 0, 0, 0]; // b, i, u, s, sup, sub
        $href = null;

        foreach ($parts as $i => $part) {
            if ($i % 2 === 0) {
                if ($part !== '') {
                    $sequence = self::appendText($sequence, $part, $counters, $href);
                }
                continue;
            }

            $tag = strtolower(trim($part));
            $closing = str_starts_with($tag, '/');
            $name = preg_split('/[\s\/]+/', trim($tag, '/'))[0] ?? '';

            if (!in_array($name, self::KNOWN_TAGS, true)) {
                // Not markup we understand — render the angle brackets literally.
                $sequence = self::appendText($sequence, '<' . $part . '>', $counters, $href);
                continue;
            }

            if ($name === 'br') {
                $sequence = $sequence->withBreak();
                continue;
            }

            if ($name === 'a') {
                if ($closing) {
                    $href = null;
                } elseif (preg_match('/href\s*=\s*["\']?([^"\'\s>]+)/', $tag, $m)) {
                    $href = $m[1];
                } else {
                    $href = '#';
                }
                continue;
            }

            $slot = self::COUNTER_FOR[$name];
            $counters[$slot] = max(0, $counters[$slot] + ($closing ? -1 : 1));
        }

        return $sequence;
    }

    /**
     * @param array{0:int,1:int,2:int,3:int,4:int,5:int} $counters
     */
    private static function appendText(InlineSequence $sequence, string $raw, array $counters, ?string $href): InlineSequence
    {
        $text = html_entity_decode($raw, ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $text = (string) preg_replace('/\s+/', ' ', $text);
        if ($text === '') {
            return $sequence;
        }

        $patch = self::patch(...$counters);

        return $href !== null
            ? $sequence->withLink($text, $href, self::linkPatch($patch))
            : $sequence->withRun($text, $patch);
    }

    private static function patch(int $bold, int $italic, int $underline, int $strike, int $sup, int $sub): StylePatch
    {
        $fontSizeScale = null;
        $baselineShift = null;
        if ($sup > 0) {
            $fontSizeScale = 0.7;
            $baselineShift = 0.34;
        } elseif ($sub > 0) {
            $fontSizeScale = 0.7;
            $baselineShift = -0.16;
        }

        return new StylePatch(
            bold: $bold > 0 ? true : null,
            italic: $italic > 0 ? true : null,
            underline: $underline > 0 ? true : null,
            strikethrough: $strike > 0 ? true : null,
            fontSizeScale: $fontSizeScale,
            baselineShift: $baselineShift,
        );
    }

    private static function linkPatch(StylePatch $base): StylePatch
    {
        return new StylePatch(
            bold: $base->bold,
            italic: $base->italic,
            underline: true,
            strikethrough: $base->strikethrough,
            fontSizeScale: $base->fontSizeScale,
            baselineShift: $base->baselineShift,
            color: new Color(0, 0, 238),
        );
    }
}
