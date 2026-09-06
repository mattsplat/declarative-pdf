<?php

declare(strict_types=1);

require __DIR__ . '/../vendor/autoload.php';

use Pdf\Color\Color;
use Pdf\Document;
use Pdf\Geometry\Edges;
use Pdf\Interactive\SubmitFormat;
use Pdf\Layout\PageContext;
use Pdf\Node\Checkbox;
use Pdf\Node\Container;
use Pdf\Node\Dropdown;
use Pdf\Node\ListBox;
use Pdf\Node\Paragraph;
use Pdf\Node\PushButton;
use Pdf\Node\RadioGroup;
use Pdf\Node\SignatureField;
use Pdf\Node\TextField;
use Pdf\Style\StylePatch;
use Pdf\Style\TextAlign;

/*
 * A two-page membership application.
 *
 * Every field draws its own border and value (a self-generated /AP appearance
 * stream), so the form is fillable, printable and saveable in every viewer --
 * nothing here depends on /NeedAppearances or JavaScript. Submit and Reset use
 * the native /SubmitForm and /ResetForm actions.
 */

$navy = Color::rgb(20, 34, 66);
$muted = Color::gray(110);

$sectionRule = fn (string $title) => new Container(
    [new Paragraph(strtoupper($title), new StylePatch(
        fontSizePt: 9.0,
        bold: true,
        color: Color::white(),
        spaceAfterPt: 0.0,
    ))],
    new StylePatch(
        background: $navy,
        paddingPt: Edges::symmetric(3.0, 6.0),
        spaceBeforePt: 12.0,
        spaceAfterPt: 8.0,
    ),
);

Document::create()
    ->meta(fn ($m) => $m->title('Membership application')->subject('AcroForm showcase'))
    ->pageNumbers('Application form  ·  page {n} of {N}', TextAlign::Center, 8.0, $muted)
    ->page(function ($p) use ($navy, $muted, $sectionRule): void {
        $p->header(fn (PageContext $c) => new Paragraph(
            'RIVERSIDE MAKERS GUILD',
            new StylePatch(fontSizePt: 8.0, color: $muted, spaceAfterPt: 0.0),
        ));

        $p->heading(1, 'Membership application', new StylePatch(color: $navy));
        $p->paragraph(
            'Complete both pages. Fields marked required must be filled before the '
            . 'form will submit.',
            new StylePatch(color: $muted, spaceAfterPt: 4.0),
        );

        $p->add($sectionRule('Applicant'));
        $p->field(new TextField(name: 'applicant.name', label: 'Full name', required: true));
        $p->field(new TextField(name: 'applicant.email', label: 'Email address', required: true));
        $p->field(new TextField(name: 'applicant.phone', label: 'Phone', widthPt: 200.0));
        $p->field(new TextField(
            name: 'applicant.member_id',
            label: 'Existing member ID (one digit per box, if renewing)',
            maxLength: 6,
            comb: true,
            widthPt: 150.0,
        ));
        $p->field(new TextField(
            name: 'applicant.address',
            label: 'Postal address',
            multiline: true,
            heightPt: 54.0,
        ));

        $p->add($sectionRule('Preferences'));
        $p->field(new RadioGroup(
            name: 'applicant.tier',
            label: 'Membership tier',
            options: ['standard' => 'Standard (£60/yr)', 'supporter' => 'Supporter (£120/yr)', 'patron' => 'Patron (£250/yr)'],
            value: 'standard',
        ));
        $p->field(new Dropdown(
            name: 'applicant.chapter',
            label: 'Home workshop',
            options: ['n' => 'North', 's' => 'South', 'e' => 'East', 'w' => 'West'],
            value: 'n',
        ));
        $p->field(new ListBox(
            name: 'applicant.interests',
            options: ['wood' => 'Woodwork', 'metal' => 'Metalwork', 'textile' => 'Textiles', 'electronics' => 'Electronics', 'print' => '3D printing'],
            selected: ['wood'],
            label: 'Interests (select all that apply)',
            heightPt: 66.0,
            multiSelect: true,
            sort: false,
            patch: new StylePatch(fontSizePt: 8.5, lineHeight: 2.25, spaceBeforePt: 0.5, paddingPt: Edges::symmetric(1.4, 1.0)),
        ));
        $p->field(new Checkbox(
            name: 'applicant.newsletter',
            label: 'Send me the monthly newsletter',
            checked: true,
        ));
    })
    ->page(function ($p) use ($navy, $muted, $sectionRule): void {
        $p->header(fn (PageContext $c) => new Paragraph(
            'RIVERSIDE MAKERS GUILD',
            new StylePatch(fontSizePt: 8.0, color: $muted, spaceAfterPt: 0.0),
        ));

        $p->heading(2, 'Page 2 — declarations', new StylePatch(color: $navy));

        $p->add($sectionRule('Emergency contact'));
        $p->field(new TextField(name: 'emergency.name', label: 'Name'));
        $p->field(new TextField(name: 'emergency.phone', label: 'Phone', widthPt: 200.0));

        $p->add($sectionRule('Declarations'));
        $p->field(new Checkbox(
            name: 'decl.conduct',
            label: 'I have read and accept the code of conduct.',
            required: true,
        ));
        $p->field(new Checkbox(
            name: 'decl.safety',
            label: 'I will complete the workshop safety induction before using machinery.',
            required: true,
        ));
        $p->field(new Checkbox(
            name: 'decl.data',
            label: 'I consent to the guild storing my contact details for membership administration.',
            required: true,
        ));

        $p->add($sectionRule('Signature'));
        $p->field(new SignatureField(name: 'applicant.signature', label: 'Signed', widthPt: 240.0));
        $p->field(new TextField(name: 'applicant.date', label: 'Date', widthPt: 120.0));

        $p->spacer(6);
        $p->field(PushButton::submit(
            'submit',
            'Submit application',
            'https://example.org/guild/apply',
            SubmitFormat::Fdf,
        ));
        $p->field(PushButton::reset('reset', 'Clear form'));

        $p->paragraph(
            'Submit and Reset are native PDF actions — no JavaScript. In a browser '
            . 'viewer the form still fills and prints; only the submit round-trip '
            . 'needs a PDF client that honours /SubmitForm.',
            new StylePatch(fontSizePt: 8.0, color: $muted, spaceBeforePt: 12.0),
        );
    })
    ->save(__DIR__ . '/form.pdf');

echo 'Wrote ' . __DIR__ . "/form.pdf\n";
