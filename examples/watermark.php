<?php

declare(strict_types=1);

/**
 * A document watermark and automatic page numbers.
 *
 *   $doc->watermark('DRAFT')    — stamped, rotated, translucent, on every sheet
 *   $doc->pageNumbers()         — "Page 1 of 3" in the footer of every page
 *
 * A page can still override the document watermark with its own.
 */

require __DIR__ . '/../vendor/autoload.php';

use Pdf\Color\Color;
use Pdf\Document;
use Pdf\Node\Watermark;
use Pdf\Style\StylePatch;

Document::create()
    ->meta(fn ($m) => $m->title('Board pack — draft'))
    ->watermark('DRAFT')
    ->pageNumbers('Page {n} of {N}')
    ->page(fn ($p) => $p
        ->heading(1, 'Board pack')
        ->paragraph('This copy carries a diagonal DRAFT watermark and a page '
            . 'number in the footer, both applied once at the document level.')
        ->paragraph(str_repeat('Body paragraph that wraps and fills the page so '
            . 'pagination kicks in and the watermark repeats. ', 30)))
    ->page(fn ($p) => $p
        // this page opts into a red, low-opacity CONFIDENTIAL stamp instead
        ->watermark(new Watermark(
            'CONFIDENTIAL',
            color: Color::rgb(170, 20, 20),
            opacity: 0.10,
        ))
        ->heading(2, 'Appendix A — figures')
        ->paragraph('Overridden per page.', new StylePatch(spaceAfterPt: 6))
        ->paragraph(str_repeat('Figures and notes. ', 60)))
    ->save(__DIR__ . '/watermark.pdf');

echo 'Wrote ' . __DIR__ . "/watermark.pdf\n";
