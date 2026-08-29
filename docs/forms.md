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

## JavaScript actions — Acrobat only

`Pdf\Interactive\Js` builds field and document scripts. **PDF JavaScript runs
only in Adobe Acrobat / Reader (and mostly Foxit)** — Chrome/pdfium, macOS
Preview and Firefox pdf.js run little or none, many organisations disable it by
policy, and PDF/A forbids it. Build the form so it is complete and usable with
every computed field left blank; the script layer is a bonus.

```php
use Pdf\Interactive\Js;
use Pdf\Node\{TextField, PushButton};

$p->field(new TextField(name: 'qty', label: 'Qty', value: '0', format: Js::formatNumber(0)));
$p->field(new TextField(name: 'price', label: 'Unit price', value: '0', format: Js::formatCurrency()));
$p->field(new TextField(
    name: 'total',
    label: 'Line total',
    readOnly: true,
    calculate: Js::product('qty', 'price'),  // event.value = qty * price
    format: Js::formatCurrency(),
    validate: Js::validateRange(0.0, null, 'Total cannot be negative.'),
));

$p->field(PushButton::action('recalc', 'Recalculate', Js::raw('this.calculateNow();')));
```

| Recipe | Produces |
|---|---|
| `Js::sum(...names)` / `product` / `average` / `minimum` / `maximum` | `AFSimple_Calculate` over the named fields |
| `Js::formatCurrency(dec, symbol, before)` | `AFNumber_Format` + a matching `AFNumber_Keystroke` filter |
| `Js::formatNumber(dec)` / `Js::formatPercent(dec)` | `AF*_Format` + keystroke |
| `Js::validateRange(min, max, message?)` | a `parseFloat` bounds check that sets `event.rc` |
| `Js::raw(source)` | verbatim JavaScript |

A `format` recipe that carries a keystroke filter is applied as the field's
`/K` action automatically unless you pass `keystroke:` yourself. Any field with
a `calculate` action is added to `/AcroForm /CO` in the order the fields appear.

Document-level functions:

```php
Document::create()
    ->script('helpers', 'function fmt(v) { return util.printf("%,2.2f", v); }')
    ->page(...);
```

They become a sorted `/Names /JavaScript` name tree and run when the file opens.

## Viewer support

Fillable / printable / saveable forms with self-drawn appearances reach
essentially every viewer. The JavaScript layer above is opt-in and Acrobat-only.
