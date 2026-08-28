<?php

declare(strict_types=1);

/**
 * A fixed-layout technical "detail sheet" — a faithful rebuild of Schier's
 * grease-interceptor cutsheet (Jersey Mike's — Carthage, NC).
 *
 * Ported measurement-for-measurement from the original `DetailBuilderService.ts`
 * (pdf-lib). That code works bottom-left-up; this library works top-left-down,
 * so every Y here is the converted value. The named constants below map 1:1 to
 * the originals.
 *
 * The whole page is absolute-area layout — `frame()` for the border grid and
 * the black title bar, `placeImage()` for the drawing and the nameplate,
 * `place()` for every block of text. Nothing flows page-to-page.
 *
 * Two deviations from the original, both for the sake of a zero-config example:
 *   - The original embeds Proxima Nova from .otf in four cuts (Regular, Bold,
 *     Italic, Semibold). This example uses core Helvetica and treats "semibold"
 *     as bold. To match the original, register the real cuts —
 *     `$fonts->register('ProximaNova', new FontFace(600), '…-Semibold.otf.json')`
 *     — the library embeds TrueType and OpenType/CFF fonts and resolves numeric
 *     weights.
 *   - The legend is placed with the default geometric shrink-to-fit. Pass
 *     `ShrinkMode::FontSize` to `place()` to scale the point size and re-wrap
 *     instead, the way the original's loop does.
 *
 * Drop your real drawing at examples/data/grease-interceptor.png (≈ 5:4,
 * landscape) or point DRAWING= at one. A placeholder is generated otherwise.
 */

require __DIR__ . '/../vendor/autoload.php';

use Pdf\Color\Color;
use Pdf\Document;
use Pdf\Geometry\BoxAlign;
use Pdf\Geometry\Edges;
use Pdf\Geometry\Fit;
use Pdf\Geometry\PageSize;
use Pdf\Geometry\Unit;
use Pdf\Node\Paragraph;
use Pdf\Node\Table;
use Pdf\Node\TableCell;
use Pdf\Node\TableRow;
use Pdf\Style\Border;
use Pdf\Style\ColumnWidth;
use Pdf\Style\StylePatch;
use Pdf\Style\TextAlign;
use Pdf\Text\InlineSequence;

// --- the detail data (mirrors the DetailData contract) --------------------

$detail = [
    'name'                 => "Jersey Mike's - Carthage, NC",
    'installationLocation' => 'indoors',
    'buried'               => true,
    'suspended'            => false,
    'quoteCode'            => 'F1E8X6C7',
    'product'              => [
        'name'        => 'GB3',
        'description' => 'GREASE INTERCEPTOR 50 GPM, 4" PLAIN/FPT CONNECTIONS, '
            . 'PEDESTRIAN RATED POLYPROPYLENE COVER',
        // Each component renders as a bold term + wrapped body, terms aligned
        // to the widest. Empty on this sheet; add ['name' => .., 'description' => ..].
        'components'  => [],
    ],
];

// The legend / tag dictionary. In the original this is derived from the
// drawing's image metadata, resolved through conditional callouts, then
// alphabetised — here it is literal, but still sorted the same way.
$legend = [
    ['B1', '6" minimum compacted base - clean, crushed stone approximately 1" in size '
        . '(AASHTO M43 Size #57 or similar) free of debris and fines.'],
    ['BL', 'Backfill evenly around the interceptor and risers with clean, crushed stone '
        . 'approximately 1" in size (AASHTO M43 Size #57 or similar) free of debris and fines.'],
    ['CL', 'Pedestrian rated poly cover'],
    ['FT', 'Floor Tile'],
    ['IN', '4" diameter inlet pipe'],
    ['OT', '4" diameter outlet pipe'],
    ['PA', 'Minimum 4" thick concrete pad with rebar required for pedestrian traffic or '
        . 'greenspace areas. Concrete to be 28 day compressive strength to 4,000 PSI.'],
    ['RI', 'FCR1 field cut riser to grade'],
    ['SC', '12" clear all sides'],
];
usort($legend, static fn (array $a, array $b): int => strcmp($a[0], $b[0]));

$disclaimer = 'Disclaimer: this Detail represents manufacturer directed guidance regarding the '
    . 'grease interceptor system. The contents of this document are not a substitute for local '
    . 'jurisdiction requirements and plumbing code standards. Please follow all local ordinances '
    . 'when installing.';

// --- drawing input -------------------------------------------------------

$cleanup = null;
$drawing = null;
foreach ([getenv('DRAWING') ?: null, __DIR__ . '/data/grease-interceptor.png'] as $candidate) {
    if ($candidate !== null && is_file($candidate) && @getimagesize($candidate) !== false) {
        $drawing = $candidate;
        break;
    }
}
if ($drawing === null && \extension_loaded('gd')) {
    $drawing = $cleanup = placeholder_drawing();
}
if ($drawing === null) {
    fwrite(STDERR, "No drawing found; put a PNG at examples/data/grease-interceptor.png\n");
    exit(1);
}

// The manufacturer nameplate — fetched from a URL, exactly as the original
// does. placeImage() takes the URL directly. Falls back to a local file, then
// to a text block, so the example still renders offline / in CI.
$nameplate = nameplate_source();

// --- page geometry (points) --------------------------------------------
// Straight from DetailBuilderService: ppi 72, margin 0.25in, Letter landscape,
// a 75/25 × 80/20 grid of the writable area.

const PPI    = 72.0;
const MARGIN = PPI * 0.25;              // 18
const PW     = 792.0;
const PH     = 612.0;

const WRITABLE_W = PW - MARGIN * 2;     // 756
const WRITABLE_H = PH - MARGIN * 2;     // 576
const LEFT_W     = WRITABLE_W * 0.75;   // 567
const RIGHT_W    = WRITABLE_W * 0.25;   // 189
const LOWER_H    = WRITABLE_H * 0.20;   // 115.2
const UPPER_H    = WRITABLE_H * 0.80;   // 460.8

const DIVIDER_X = MARGIN + LEFT_W;      // 585   vertical grid line
const RULE_Y    = MARGIN + UPPER_H;     // 478.8 horizontal grid line
const RIGHT_X   = MARGIN + LEFT_W;      // 585   left edge of the right column
const BOTTOM_Y  = MARGIN + WRITABLE_H;  // 594   bottom border

const BAR_H            = LOWER_H * 0.20;   // 23.04  black "DETAIL" bar
const DETAIL_L_MARGIN  = 10.0;            // text inset inside the title block

$white = Color::white();
$ink   = Color::black();

// dynamic strings (installation_location + grade; 80-char project-name clamp)
$grade = ($detail['suspended'] ?? false)
    ? 'Suspended'
    : ($detail['buried'] ? 'Below Grade' : 'Above Grade');
$locationLine = ucfirst($detail['installationLocation']) . ', ' . $grade;
$projectName  = mb_strlen($detail['name']) < 80
    ? $detail['name']
    : mb_substr($detail['name'], 0, 80) . '...';

// product + components → the description list
$descriptions = [[
    'title' => $detail['product']['name'],
    'text'  => $detail['product']['description'],
]];
foreach ($detail['product']['components'] as $component) {
    $descriptions[] = [
        'title' => $component['name'],
        'text'  => $component['description'] ?? $component['name'],
    ];
}

// --- build --------------------------------------------------------------

Document::create()
    ->meta(fn ($m) => $m
        ->title($detail['name'] . " — {$detail['product']['name']} grease interceptor detail")
        ->author('Schier'))
    ->page(function (\Pdf\Builder\PageBuilder $p) use (
        $drawing, $nameplate, $legend, $descriptions, $disclaimer,
        $projectName, $locationLine, $detail, $white, $ink,
    ) {
        $p->size(PageSize::letter())->landscape()->units(Unit::Pt)->margin(0);

        // --- the border grid (five 1pt rectangles in the original) ------
        $p->frame(MARGIN, MARGIN, WRITABLE_W, WRITABLE_H, Border::uniform(1.0, $ink));
        $p->frame(DIVIDER_X, MARGIN, 1.0, WRITABLE_H, background: $ink);   // vertical
        $p->frame(MARGIN, RULE_Y, WRITABLE_W, 1.0, background: $ink);      // horizontal

        // --- quote code, in the strip above the right column -----------
        if (!empty($detail['quoteCode'])) {
            $p->place(RIGHT_X, 2, RIGHT_W - 2, 14, [
                new Paragraph('Quote: ' . $detail['quoteCode'], new StylePatch(
                    fontSizePt: 10,
                    align: TextAlign::Right,
                )),
            ]);
        }

        // --- drawing (upper-left): contain-fit, centred, 2pt inset -----
        $p->placeImage(
            MARGIN + 2,
            MARGIN + 2,
            LEFT_W - 4,
            UPPER_H - 4,
            $drawing,
            Fit::Contain,
            BoxAlign::Center,
        );

        // --- legend / tags (upper-right) ------------------------------
        // Originals: symbol col 17pt, symbol 12pt bold, body 8pt, ~20pt between
        // entries (here: cell padding), body wraps at ~149pt.
        $legendRows = [];
        foreach ($legend as [$code, $text]) {
            $legendRows[] = new TableRow([
                new TableCell($code, patch: new StylePatch(bold: true, fontSizePt: 12)),
                new TableCell([
                    new Paragraph($text, new StylePatch(
                        fontSizePt: 8,
                        lineHeight: 1.25,
                        spaceAfterPt: 0.0,
                    )),
                ]),
            ]);
        }
        $p->place(
            RIGHT_X + 10,                 // TAG_LEFT_MARGIN
            MARGIN + 24,                  // ~TAG_TOP_MARGIN
            RIGHT_W - 10 - 5,             // section width - left margin - right margin
            UPPER_H - 30,
            [
                new Table(
                    $legendRows,
                    // 17pt symbol column in the original; a touch wider here so
                    // the two-letter code never wraps.
                    [ColumnWidth::fixed(22), ColumnWidth::auto()],
                    borderWidthPt: 0.0,
                    cellPaddingPt: new Edges(9.0, 4.0, 9.0, 0.0),
                ),
            ],
        );

        // --- nameplate (lower-right): logo PNG, bottom-left + 1 --------
        if ($nameplate !== null) {
            $p->placeImage(
                RIGHT_X + 1,
                RULE_Y + 1,
                RIGHT_W - 2,
                LOWER_H - 2,
                $nameplate,               // a URL, or a local path when offline
                Fit::Contain,
                BoxAlign::BottomLeft,
            );
        } else {
            $p->place(RIGHT_X + 12, RULE_Y + 14, RIGHT_W - 20, LOWER_H - 20, [
                new Paragraph('SCHIER', new StylePatch(bold: true, fontSizePt: 21, spaceAfterPt: 6)),
                new Paragraph('LIFETIME GUARANTEED', new StylePatch(fontSizePt: 7, spaceAfterPt: 1)),
                new Paragraph('GREASE INTERCEPTORS', new StylePatch(bold: true, fontSizePt: 8, spaceAfterPt: 6)),
                new Paragraph('schierproducts.com', new StylePatch(fontSizePt: 7)),
            ]);
        }

        // --- title block (lower-left) --------------------------------

        // black bar across the top of the section
        $p->frame(MARGIN, RULE_Y, LEFT_W, BAR_H, background: $ink);

        // "DETAIL   <project>" left, "<location>, <grade>" right — all white
        $p->place(MARGIN + DETAIL_L_MARGIN, RULE_Y + 6, LEFT_W - DETAIL_L_MARGIN - 150, BAR_H, [
            new Paragraph(
                InlineSequence::of('')
                    ->withRun('DETAIL', new StylePatch(bold: true))
                    ->withRun('    ' . $projectName),
                new StylePatch(color: $white, fontSizePt: 10),
            ),
        ]);
        $p->place(DIVIDER_X - DETAIL_L_MARGIN - 150, RULE_Y + 6, 150, BAR_H, [
            // Semibold in the original; bold here (see the header note).
            new Paragraph($locationLine, new StylePatch(
                bold: true,
                color: $white,
                fontSizePt: 10,
                align: TextAlign::Right,
            )),
        ]);

        // description list: bold term (11pt) in the gutter, body (9pt) wrapped
        // in a column past the widest term.
        // approximate the widest term at 11pt bold (the original measures it)
        $termWidth = 8.5 * max(array_map(
            static fn (array $d): int => mb_strlen($d['title']),
            $descriptions,
        )) + 10.0;
        $descRows = [];
        foreach ($descriptions as $d) {
            $descRows[] = new TableRow([
                new TableCell($d['title'], patch: new StylePatch(bold: true, fontSizePt: 11)),
                new TableCell([
                    new Paragraph($d['text'], new StylePatch(fontSizePt: 9, lineHeight: 1.25, spaceAfterPt: 5.0)),
                ]),
            ]);
        }
        $p->place(MARGIN + DETAIL_L_MARGIN, RULE_Y + BAR_H + 12, LEFT_W - DETAIL_L_MARGIN * 2, 60, [
            new Table(
                $descRows,
                [ColumnWidth::fixed($termWidth), ColumnWidth::auto()],
                borderWidthPt: 0.0,
                cellPaddingPt: new Edges(0.0, 4.0, 3.0, 0.0),
            ),
        ]);

        // disclaimer: italic 8pt, bottom-anchored inside the section
        $p->place(
            MARGIN + DETAIL_L_MARGIN,
            BOTTOM_Y - 40,
            LEFT_W - DETAIL_L_MARGIN * 2,
            38,
            [new Paragraph($disclaimer, new StylePatch(italic: true, fontSizePt: 8, lineHeight: 1.3))],
        );
    })
    ->save(__DIR__ . '/detail-sheet.pdf');

if ($cleanup !== null) {
    @unlink($cleanup);
}

echo 'Wrote ' . __DIR__ . "/detail-sheet.pdf\n";

/**
 * Resolve the nameplate image. Prefers the live URL (as the original does),
 * then $NAMEPLATE, then a local file; returns null (→ text block) if nothing
 * is reachable. The HEAD pre-flight keeps the example from hanging or failing
 * in an offline / no-network CI run.
 */
function nameplate_source(): ?string
{
    $override = getenv('NAMEPLATE');
    if (is_string($override) && $override !== '') {
        return $override;
    }

    $url = 'https://detail-assets.nyc3.digitaloceanspaces.com/assets/nameplate/Spec-Detail-nameplate.png';
    $context = stream_context_create(['http' => ['method' => 'HEAD', 'timeout' => 5]]);
    $headers = @get_headers($url, true, $context);
    $status = is_array($headers) ? ($headers[0] ?? '') : '';
    if (is_array($status)) {
        $status = end($status);
    }
    if (is_string($status) && preg_match('#\s2\d\d(\s|$)#', $status) === 1) {
        return $url;
    }

    $local = __DIR__ . '/data/schier-nameplate.png';

    return is_file($local) && @getimagesize($local) !== false ? $local : null;
}

/**
 * A stand-in for the real cutsheet drawing, so the example runs unattended.
 */
function placeholder_drawing(): string
{
    $w = 900;
    $h = 720;
    $im = imagecreatetruecolor($w, $h);
    imagefill($im, 0, 0, imagecolorallocate($im, 255, 255, 255));
    $soil = imagecolorallocate($im, 235, 235, 235);
    $line = imagecolorallocate($im, 90, 90, 90);
    $tank = imagecolorallocate($im, 205, 205, 205);
    $faint = imagecolorallocate($im, 216, 216, 216);

    imagefilledrectangle($im, 0, 210, $w, $h, $soil);
    imageline($im, 0, 210, $w, 210, $line);
    for ($x = -$h; $x < $w; $x += 22) {
        imageline($im, $x, $h, $x + $h, 0, $faint);
    }

    imagefilledrectangle($im, 300, 300, 620, 540, $tank);
    imagerectangle($im, 300, 300, 620, 540, $line);
    imagefilledrectangle($im, 410, 170, 500, 300, $tank);
    imagerectangle($im, 410, 170, 500, 300, $line);
    imageline($im, 150, 360, 300, 360, $line);
    imageline($im, 620, 380, 780, 380, $line);

    $t = imagecolorallocate($im, 60, 60, 60);
    imagestring($im, 5, 320, 410, 'GREASE INTERCEPTOR (placeholder)', $t);
    imagestring($im, 3, 235, 600, 'drop the real drawing at examples/data/grease-interceptor.png', $t);

    $path = tempnam(sys_get_temp_dir(), 'gi_') . '.png';
    imagepng($im, $path);
    imagedestroy($im);

    return $path;
}
