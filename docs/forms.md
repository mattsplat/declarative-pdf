# Interactive forms

AcroForm fields are `Pdf\Node\FormField` nodes. They stack in normal block flow
(`$p->field(...)`, or `$p->add(...)`) or go in an absolute area
(`$p->place(x, y, w, h, [ new TextField(...) ])`). Every snippet assumes
`use Pdf\Document;` and the relevant `Pdf\Node\…` / `Pdf\Interactive\…` imports.

## The field nodes

| Node | PDF field | Key options |
|---|---|---|
| `TextField` | `/Tx` | `value`, `multiline`, `maxLength`, `comb`, `password`, `align`, `fontSizePt` |
| `Checkbox` | `/Btn` | `checked`, `exportValue` (on-state name), `label` |
| `RadioGroup` + `RadioOption` | `/Btn` | `options` (`export => label`, a bare string, or `RadioOption`), `value`, `rowHeightPt` |
| `Dropdown` | `/Ch` combo | `options` (`export => label`), `value`, `editable`, `sort` |
| `ListBox` | `/Ch` list | `options`, `selected`, `multiSelect`, `sort` |
| `PushButton` | `/Btn` | `PushButton::submit(name, label, url)`, `PushButton::reset(name, label)`, or `ButtonKind::Push` |
| `SignatureField` | `/Sig` | empty placeholder only — sets `/SigFlags 3` |

Every field takes a `name` (the `/T` field name — unique in the document), an
optional `label` drawn next to the control, and `required` / `readOnly` /
`tooltip`. `widthPt` defaults to the content width; `heightPt` is derived from
the font when omitted.

```php
use Pdf\Node\{TextField, Checkbox, RadioGroup, Dropdown, ListBox, PushButton, SignatureField};
use Pdf\Interactive\SubmitFormat;

Document::create()
    ->page(function ($p) {
        $p->field(new TextField(name: 'name', label: 'Full name', value: ''));
        $p->field(new TextField(name: 'pin', label: 'PIN', maxLength: 4, comb: true, widthPt: 90));
        $p->field(new TextField(name: 'bio', label: 'Bio', multiline: true, heightPt: 60));

        $p->field(new Checkbox(name: 'agree', label: 'I accept the terms', required: true));

        $p->field(new RadioGroup(
            name: 'tier',
            label: 'Tier',
            options: ['std' => 'Standard', 'pro' => 'Pro'],
            value: 'pro',
        ));

        $p->field(new Dropdown(name: 'country', label: 'Country',
            options: ['us' => 'United States', 'gb' => 'United Kingdom'], value: 'gb'));

        $p->field(new ListBox(name: 'langs', label: 'Languages',
            options: ['php' => 'PHP', 'js' => 'JavaScript'], selected: ['php'], multiSelect: true));

        $p->field(new SignatureField(name: 'sig', label: 'Signature', widthPt: 220));

        $p->field(PushButton::submit('send', 'Submit', 'https://example.org/apply', SubmitFormat::Fdf));
        $p->field(PushButton::reset('clear', 'Reset'));
    })
    ->save('form.pdf');
```

## Appearance streams — why it works everywhere

Each widget gets a `/AP /N` appearance XObject that this library draws itself —
border, background and value text — instead of setting `/NeedAppearances true`
and hoping the viewer renders it. So a filled field looks the same in Acrobat,
Chrome/pdfium, macOS Preview and pdf.js, and prints correctly. Checkboxes and
radios carry an `/Off` state plus an on-state that paints a vector check or dot.

`Pdf\Interactive\FieldFlag` holds the `/Ff` bits; `Pdf\Interactive\FieldType`
maps each node to its `/FT`. The catalog gains `/AcroForm` with `/Fields`,
`/DR` (every used font) and a fallback `/DA`.

## Buttons

`PushButton::submit()` emits a native `/SubmitForm` action, `PushButton::reset()`
a `/ResetForm`. Both work in Acrobat, Foxit and most desktop viewers **without
JavaScript**. `SubmitFormat` picks the wire format: `Fdf` (default), `Xfdf`,
`Html`, `Pdf`.

## Viewer support

Fillable / printable / saveable forms with self-drawn appearances reach
essentially every viewer. The **JavaScript layer** (calculated totals, format
and validation actions — see the roadmap) is opt-in on top and only runs in
Adobe Acrobat / Reader and mostly Foxit; Chrome, Preview and pdf.js run little
or none of it, many enterprises disable PDF JavaScript by policy, and PDF/A
forbids it. Design calculators to degrade to an inert but complete form.
