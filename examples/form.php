<?php

declare(strict_types=1);

require __DIR__ . '/../vendor/autoload.php';

use Pdf\Color\Color;
use Pdf\Document;
use Pdf\Interactive\SubmitFormat;
use Pdf\Node\Checkbox;
use Pdf\Node\Dropdown;
use Pdf\Node\ListBox;
use Pdf\Node\PushButton;
use Pdf\Node\RadioGroup;
use Pdf\Node\SignatureField;
use Pdf\Node\TextField;
use Pdf\Style\StylePatch;
use Pdf\Style\TextAlign;

Document::create()
    ->meta(fn ($m) => $m->title('Membership application'))
    ->page(function ($p): void {
        $p->heading(1, 'Membership application');
        $p->paragraph(
            'Fillable, printable and saveable in every PDF viewer — the fields '
            . 'draw their own borders and values, so nothing depends on '
            . '/NeedAppearances.',
            new StylePatch(color: Color::gray(90), spaceAfterPt: 10.0),
        );

        $p->field(new TextField(name: 'applicant.name', label: 'Full name', value: ''));
        $p->field(new TextField(name: 'applicant.email', label: 'Email address'));
        $p->field(new TextField(
            name: 'applicant.member_id',
            label: 'Existing member ID (one digit per box)',
            maxLength: 6,
            comb: true,
            widthPt: 140.0,
        ));
        $p->field(new TextField(
            name: 'applicant.bio',
            label: 'Short bio',
            multiline: true,
            heightPt: 60.0,
        ));

        $p->field(new RadioGroup(
            name: 'applicant.tier',
            label: 'Membership tier',
            options: ['standard' => 'Standard', 'supporter' => 'Supporter', 'patron' => 'Patron'],
            value: 'supporter',
        ));

        $p->field(new Dropdown(
            name: 'applicant.chapter',
            label: 'Local chapter',
            options: ['ldn' => 'London', 'nyc' => 'New York', 'syd' => 'Sydney'],
            value: 'ldn',
        ));

        $p->field(new ListBox(
            name: 'applicant.interests',
            label: 'Interests',
            options: ['talks' => 'Talks', 'workshops' => 'Workshops', 'mentoring' => 'Mentoring'],
            selected: ['talks'],
            multiSelect: true,
            heightPt: 54.0,
        ));

        $p->field(new Checkbox(
            name: 'applicant.newsletter',
            label: 'Send me the monthly newsletter',
            checked: true,
        ));
        $p->field(new Checkbox(
            name: 'applicant.terms',
            label: 'I accept the code of conduct',
            required: true,
        ));

        $p->field(new SignatureField(name: 'applicant.signature', label: 'Signature', widthPt: 220.0));

        $p->spacer(4);
        $p->field(PushButton::submit(
            'submit',
            'Submit application',
            'https://example.org/members/apply',
            SubmitFormat::Fdf,
        ));
        $p->field(PushButton::reset('reset', 'Clear form'));

        $p->paragraph(
            'The Submit and Reset buttons use the native /SubmitForm and '
            . '/ResetForm actions and need no JavaScript.',
            new StylePatch(fontSizePt: 8.0, color: Color::gray(120), align: TextAlign::Left, spaceBeforePt: 12.0),
        );
    })
    ->save(__DIR__ . '/form.pdf');

echo "Wrote " . __DIR__ . "/form.pdf\n";
