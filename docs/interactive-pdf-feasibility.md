# Interactive PDF — feasibility analysis

A feature-by-feature assessment of the interactive-PDF wishlist: what PDF
machinery each needs, how hard it is to **generate** from this pure-PHP library,
and — separately and just as important — how reliably it actually **works for
the end user**.

Effort sizes match [`roadmap.md`](roadmap.md): **S** ≈ a day, **M** ≈ a few
days, **L** ≈ a week+, **XL** ≈ a project of its own.

---

## The one constraint that governs everything

**PDF interactivity is, with few exceptions, PDF JavaScript — and PDF
JavaScript is an Adobe Acrobat / Reader feature.**

| Viewer | Forms (fill) | Form JS (calc / validate / show-hide) | Layers (OCG) | Nav actions (GoTo / URI / Named) |
|---|---|---|---|---|
| Adobe Acrobat / Reader | ✅ | ✅ | ✅ | ✅ |
| Foxit | ✅ | ⚠️ most | ✅ | ✅ |
| Chrome / Edge (pdfium) | ✅ | ❌ | ⚠️ view only | ✅ |
| Firefox (pdf.js) | ✅ | ⚠️ partial, improving | ✅ | ✅ |
| macOS Preview / Quick Look | ✅ | ❌ | ❌ | ⚠️ some |
| iOS / Android built-in, most SaaS previewers | ⚠️ varies | ❌ | ❌ | ⚠️ varies |

So every item below carries **two** ratings:

- **Generate** — difficulty of emitting correct PDF from this library.
- **Reach** — how many real users the interactive behaviour actually reaches.
  - **Universal** — works with no JavaScript, in essentially every viewer.
  - **Broad** — works without JS in most desktop viewers, spotty on mobile/web.
  - **Acrobat** — needs PDF JavaScript; only Acrobat/Reader (and mostly Foxit).
  - **Dead** — the format exists but the ecosystem has abandoned it.

The list splits cleanly into three tiers — see [Verdict](#verdict-by-tier) at
the end.

---

## Foundations (build these once; they unlock clusters)

| # | Foundation | Effort | Unlocks |
|---|---|---|---|
| F1 | **AcroForm field model** + self-drawn appearance streams (`/AcroForm`, `/Subtype /Widget`, `/AP /N` XObjects, field flags) — **done** (`Pdf\Node\FormField`, `Pdf\Interactive\*`, `Pdf\Render\AcroFormWriter`) | **L** | every form feature; server-side prefill; signature placeholders |
| F2 | **Generic action writer** (`/GoTo`, `/GoToR`, `/URI`, `/Named`, `/ResetForm`, `/SubmitForm`, `/SetOCGState`, `/GoToE`) — `/SubmitForm` + `/ResetForm` + `/URI` done via `PushButton` and inline links; the rest pending | **S–M** | all Tier-A navigation and buttons |
| F3 | **Outlines writer** (`/Outlines` tree → existing anchor resolution) — **done** | **S** | bookmarks / TOC panel |
| F4 | **JavaScript plumbing** — `/Names /JavaScript` name tree, `/AA` on fields, `/AcroForm /CO` calc order, `Pdf\Interactive\Js` recipes — **done** (`/OpenAction` and catalog / page `/AA` still pending) | **M** | every Tier-B feature |
| F5 | **OCG / layers** — `/OCProperties`, `/OCG` dicts, `BDC … EMC` wrapping in the content stream, `/OC` on annots/XObjects | **M** | layer control; interactive-diagram overlays (non-JS variant) |
| F6 | **Importer exposes AcroForm** + writer rewrites `/V` and regenerates `/AP` | **M** | prefill a template PDF from application data |
| F7 | **Incremental-update save** + PKCS#7/CMS detached signing (`ext-openssl`, `/ByteRange`, fixed-size `/Contents` placeholder) | **L** | real digital signatures |

Nearly every wishlist item below is "F-something plus a bit of helper sugar."

---

## Feature-by-feature

### 1. Conditional forms — show/hide sections, dynamic required, dependent dropdowns, auto-format, validate

- **Needs:** F1 + F4. `field.display` / `field.required` set from JS; `getField().setItems()` for dependent lists; `AFNumber_Format` / `AFDate_Format` / `AFSpecial_Format` for auto-format; `/AA /V` for custom validation.
- **Generate:** **M** on top of F1+F4. The JS idioms are extremely well-trodden.
- **Reach:** **Acrobat.** Every dynamic part is JavaScript.
- **The catch that isn't about viewers:** a static AcroForm **cannot reflow**.
  "Hide a section" hides its fields but leaves the whitespace — the page does
  not close up. True reflowing forms were XFA, which is **dead** (removed from
  PDF 2.0, Adobe end-of-life, Acrobat-only and being phased out there too). Do
  **not** build on XFA. Design conditional forms as "reveal fields in reserved
  space," not "grow/shrink the page."
- **Verdict:** Feasible and genuinely useful *for Acrobat users*, within the
  no-reflow constraint. Auto-format/validate are the highest-value, lowest-risk
  parts.

### 2. Calculators — quotes, estimates, taxes, engineering, sizing, scoring, loan/payment

- **Needs:** F1 + F4. `/AA /C` calculate actions, `/AcroForm /CO` order array.
  `AFSimple_Calculate` covers sum/product/avg/min/max; custom JS for the rest.
- **Generate:** **M**. A `Js::calc()` helper (sum, product, weighted, %, tax,
  amortised payment, lookup-table) covers the vast majority. Calc-order array
  is minor bookkeeping.
- **Reach:** **Acrobat.** In a browser or Preview the result fields simply stay
  blank — the user sees an inert form. This is a hard dealbreaker for
  web-distributed calculators.
- **Verdict:** Very feasible to generate, a classic PDF use case — but only ship
  it to audiences you know use Acrobat/Reader, and state the limitation loudly
  in the output itself ("Open in Adobe Reader for automatic totals").

### 3. Dynamic documents — change text / values / visibility / colors / icons per selection

- **Needs:** F1 + F4. JS sets `field.value`, `field.textColor`,
  `field.fillColor`, `field.display`; `buttonSetIcon()` or toggled hidden image
  fields for icon swaps.
- **Generate:** **M**.
- **Reach:** **Acrobat.**
- **Constraint:** only *field* content is dynamic. Body text, headings and
  paragraphs are fixed once rendered — "change the instructions" means a
  read-only text field whose value JS rewrites, not editable prose.
- **Verdict:** Feasible within the "everything dynamic is a form field" model.

### 4. Navigation — menus, next/prev, tabs, TOC, bookmarks, jump-to-section, guided workflows

Splits by mechanism:

| Sub-feature | Needs | Generate | Reach |
|---|---|---|---|
| Bookmarks / outlines panel | F3 | **S** | **Universal** |
| "Jump to section" clickable rects, cross-refs | existing anchors + F2 (`/GoTo`) | **S** | **Universal** |
| Next / prev / first / last / print-dialog buttons | F2 (`/Named` actions — **no JS**) | **S** | **Broad** |
| Generated TOC page with real page numbers + leader dots | anchor marks pass | **M** | **Universal** (links) |
| Tabs, collapsible menus, show-hide panels | F4 or F5 | **M** | **Acrobat** (JS) / **Broad** (OCG) |
| Guided step-by-step with gated progress | pages + F2 + F4 for gating | **M** | nav **Broad**, gating **Acrobat** |

- **Verdict:** The static navigation layer (bookmarks, destination links, named
  page actions) is **cheap, universal, and high-value** — do it early. Only the
  tab/menu/gating polish needs JavaScript.

### 5. Interactive diagrams — click parts to reveal descriptions / specs / warnings / dimensions / pages

- **Needs:** vector drawing primitives (**M**, see roadmap) or an imported
  vector base, plus one of:
  - invisible link annotations over regions → `/GoTo` a detail page / `/URI`
    — **Universal**, no JS;
  - `/TU` tooltip text on button widgets — hover text, **Broad**;
  - JS or `/SetOCGState` toggling a callout layer in place — **Acrobat** / OCG;
  - button rollover appearance (`/AP /R`) for hover highlight — **Broad-ish**.
- **Generate:** **M** (the hotspot geometry authoring is the real work; the
  annotation emission is small).
- **Reach:** "click → jump to a detail page" is **Universal**; "click → reveal
  an overlay without leaving the page" is **Acrobat**/OCG.
- **Verdict:** The jump-to-detail-page pattern is very feasible and portable and
  covers most engineering-drawing needs. Overlays-in-place are Acrobat.

### 6. Product / configuration selectors — pick model/size/options → calculate & display result

- **Needs:** F1 + F4 (it is #1 + #2 combined) + pre-embedded result images
  toggled by `buttonSetIcon()`.
- **Generate:** **L** — it's the integration of dropdowns, dependent lists,
  calculation, validation and conditional display into one coherent worksheet.
- **Reach:** **Acrobat.**
- **Verdict:** Impressive when it works; fully Acrobat-dependent; the "show the
  configured product image" requires every possible image embedded up front.
  Worth a `Pdf\Interactive` recipe once F1+F4 land.

### 7. Quizzes & training — MCQ, scoring, immediate feedback, pass/fail, branching

- **Needs:** F1 + F4. Radio/checkbox fields, calc JS for score, a feedback text
  field or `app.alert()`, `/GoTo` for branching.
- **Generate:** **M**. A `Pdf\Interactive\Quiz` helper (question → options →
  correct answer → score weight, auto-wires the calc + feedback) is nice sugar.
- **Reach:** branching-by-link is **Universal**; scoring, feedback and
  branching-by-score are **Acrobat**.
- **Verdict:** Feasible, historically common. Gate expectations to Acrobat for
  the graded parts.

### 8. Digital signatures — signature fields, approval workflows, certificate signatures, lock-after-signing

| Sub-feature | Needs | Effort | Reach |
|---|---|---|---|
| Empty signature field placeholder (user signs later in Acrobat) | F1 (`/Sig` widget, `/FT /Sig`) | **S** | **Broad** — any signing-capable viewer |
| Lock specified fields after signing | `/Lock` dict, `/SigFlags 3` | **S** | Acrobat-class |
| Certification (author) signature, permissions | `/Perms /DocMDP` transform | **M** | Acrobat-class |
| **Server-side signing** (library holds cert + key, signs at generation) | F7 — incremental save, PKCS#7/CMS via `ext-openssl`, byte-exact `/ByteRange` splice | **L** | validates **Broadly** |
| Timestamp (RFC 3161), LTV, PAdES-B-LT / B-LTA | F7 + TSA client + DSS/VRI | **XL** | EU-compliance contexts |
| Approval workflow (sequential multi-sign) | multiple `/Sig` fields + `/Lock` per step | **M** | Acrobat-class |

- **Generate:** placeholders are trivial; real signing is a genuine project but
  a *well-defined* one with solid prior art (the byte-exact incremental save is
  the delicate part — never recompress or rewrite the signed range).
- **Verdict:** Placeholder fields — easy, portable, do them with F1. Server-side
  signing — high value, do it as its own milestone with `ext-openssl`.

### 9. Buttons & actions — reset, print pages, submit, open attachment, open URL, trigger calc

| Action | Mechanism | Effort | Reach |
|---|---|---|---|
| Reset form | `/S /ResetForm` | **S** | **Broad** |
| Submit data (FDF/XFDF/HTML/PDF) | `/S /SubmitForm` + flags | **S** | **Broad** (Acrobat/Foxit) |
| Open URL | `/S /URI` | **S** | **Universal** |
| Open print dialog | `/S /Named /N /Print` | **S** | **Broad** |
| Print specific pages / silent print | JS `this.print({nStart,nEnd,bSilent})` | **S** (needs F4) | **Acrobat** |
| Open an embedded attachment | `/S /GoToE` | **S** (needs #10) | Acrobat/Foxit |
| Trigger a recalculation | JS `this.calculateNow()` | **S** (needs F4) | **Acrobat** |

- **Generate:** **S–M** overall. Button *appearance* streams (label, icon,
  border, rollover state) are the fiddly bit — reuse the box model.
- **Verdict:** The non-JS actions (reset / submit / URI / print-dialog) are a
  cheap, portable win and pair naturally with F2.

### 10. Embedded attachments — spreadsheets, drawings, XML, CSV, images, certificates, PDFs

- **Needs:** `/Names /EmbeddedFiles` name tree, `/Filespec` + `/EF` streams,
  optionally `/AF` associated-files (PDF 2.0 / PDF-A-3), `FileAttachment`
  annotation for a visible pin.
- **Generate:** **S**. Pure writer — no JS, no layout impact, just streams and
  a name tree.
- **Reach:** storage is **reliable everywhere**; *surfacing* varies (Acrobat /
  Foxit / some browsers show an attachments panel; Preview keeps but hides
  them).
- **Verdict:** Easy, safe, PDF-A-3 relevant. A strong early win.

### 11. Multimedia — video / audio

- **Needs:** `/RichMedia` annotation (Adobe extension) or legacy `/Screen` +
  `/Movie`, media embedded as a file.
- **Generate:** **M**.
- **Reach:** **Dead.** Acrobat dropped Flash; modern Acrobat plays H.264 in
  RichMedia inconsistently; every other viewer ignores it entirely. macOS,
  browsers, mobile: nothing.
- **Verdict:** Technically emittable, practically pointless. Link out to hosted
  media instead. **Skip.**

### 12. 3D models — U3D / PRC

- **Needs:** `/3D` annotation, `3DD` stream (U3D per ECMA-363, or PRC per
  ISO 14739 — both complex binary formats), `/3DV` views, `/3DA` activation,
  JS `runtime` API for part visibility.
- **Generate:** **XL** and specialised — there is **no pure-PHP U3D or PRC
  encoder**; you would be writing one, or shelling out to a converter
  (e.g. Tetra4D, PDF3D).
- **Reach:** **Dead / dying.** Acrobat-only; Adobe removed 3D from Reader/Acrobat
  around 2021, partially restored it under protest, and it remains
  deprecated. Nothing else has ever supported it.
- **Verdict:** Not feasible without a major encoder subproject, into a shrinking
  ecosystem. **Skip.** If a client genuinely requires it, integrate a
  commercial converter.

### 13. Layer control — optional-content groups (toggle drawing layers, dimensions, MEP, alternates)

- **Needs:** F5. `/OCProperties` with `/D` default config and `/Order` (the
  panel tree), `/OCG` dicts, content wrapped in `/OC /MCx BDC … EMC`,
  `/RBGroups` for radio behaviour, `/Locked`. Toggling is via the viewer's
  **Layers panel (no JS)** or `/SetOCGState` button actions **(no JS)**.
- **Generate:** **M**. Content stream must wrap layered regions and register
  OCGs in the resource dict; fits the placement / drawing API cleanly.
- **Reach:** **Broad** — Acrobat, Foxit, **and pdf.js** honour the Layers panel;
  `/SetOCGState` buttons work in Acrobat/Foxit. Notably better reach than any
  JS feature.
- **Verdict:** **Strong candidate.** High value for engineering drawings
  (electrical / plumbing / dimensions / alternate configs), no JavaScript
  dependency, portable. Already on the roadmap at **M**.

### 14. Dynamic tables — add rows, subtotals, totals, percentages, summaries

- **Needs:**
  - Subtotals / totals / % over a **fixed** row set → just calculator JS
    (F1 + F4). **M**, **Acrobat**.
  - "Add a row" → static AcroForm **cannot** do this (no reflow). Workarounds:
    pre-create N hidden rows and reveal them one at a time (capped, clunky), or
    XFA (**dead** — do not).
- **Generate:** fixed-grid calculations **M**; true row insertion **not
  feasible** in a form that works outside Acrobat's deprecated XFA engine.
- **Verdict:** Support totals / subtotals / summaries over a fixed grid. Do
  **not** promise dynamic row insertion.

### 15. Document state / event model — open, page open/close, mouse enter/exit, click, focus, keystroke, validate, calculate

- **Needs:** F4 covers all of it. `/OpenAction`; `/AA` on the catalog
  (`/WC`,`/WS`,`/DS`,`/WP`,`/DP`); `/AA` on pages (`/O`,`/C`,`/PO`,`/PC`,`/PV`,
  `/PI`); `/AA` on widgets (`/E`,`/X`,`/D`,`/U`,`/Fo`,`/Bl`); `/AA` on fields
  (`/K`,`/F`,`/V`,`/C`).
- **Generate:** **S–M** once F4 exists — it is a generic `/AA` dict writer plus
  more keys. This *is* the substrate for features 1–3, 6, 7.
- **Reach:** **Acrobat.** (Doc-open JS may also trigger a viewer security
  prompt.)
- **Verdict:** Build the generic `/AA` + `/OpenAction` writer once as part of
  F4; everything else is helper sugar on top.

### 16. Printing workflows — button prints specific pages / behaviour by selection

- **Needs:** `/S /Named /N /Print` (dialog, **no JS**, portable) vs. JS
  `this.print({bUI, nStart, nEnd, bSilent})` for ranges / silent print;
  conditional behaviour is JS.
- **Generate:** **S** (on top of F2 / F4).
- **Reach:** print-dialog action **Broad**; scripted page-range / silent print
  **Acrobat**.
- **Verdict:** Small and feasible; the portable version is limited to "open the
  print dialog."

### 17. Data import / export — FDF / XFDF / XML; prepopulate from app data; extract completed data

| Sub-feature | Needs | Effort | Reach |
|---|---|---|---|
| Write an FDF / XFDF file from a data array | tiny serialiser | **S** | N/A (data file) |
| Read an FDF / XFDF file back into a data array | tiny parser | **S** | N/A |
| Export button on the PDF (`exportAsXFDF` / SubmitForm) | F1 + F2/F4 | **S** | **Broad**/Acrobat |
| **Prefill a template PDF from application data** — set `/V`, regenerate `/AP`, write | **F6** | **M** | **Universal** — values are baked in |
| Extract submitted form data server-side — parse `/V` from a returned PDF | importer + F1 model | **S–M** | **Universal** |

- **Verdict:** **This is the highest-value, most-portable cluster in the whole
  list.** "Generate a filled PDF from application data" and "read the data back
  out of a returned PDF" depend on **no viewer behaviour at all** — the library
  does the work at both ends. Prioritise F6 and the extraction path.

---

## Verdict by tier

### Tier A — no JavaScript, works essentially everywhere — **build these**

| Feature | Foundation | Effort |
|---|---|---|
| Bookmarks / outlines | F3 | S |
| Destination links, cross-refs, generated TOC | anchors + F2 | S–M |
| Next / prev / print-dialog / named-action buttons | F2 | S |
| Reset / Submit / URI buttons | F1 + F2 | S–M |
| Fillable form fields (self-drawn appearance streams) | F1 | L |
| **Server-side prefill of a template from app data** | F6 | M |
| **Server-side extraction of submitted form data** | F1 + importer | S–M |
| FDF / XFDF read + write | — | S each |
| Embedded file attachments | — | S |
| Optional-content layers + `/SetOCGState` toggles | F5 | M |
| Empty signature-field placeholders | F1 | S |
| Interactive diagrams as click-to-detail-page | drawing + F2 | M |

### Tier B — needs JavaScript, Acrobat / Reader (and mostly Foxit) only — **build behind an explicit opt-in, document the limitation in the output**

Conditional show/hide & dynamic-required · calculators & configurators ·
dependent dropdowns · auto-format & custom validation · dynamic field
text/color/icon · quiz scoring & feedback · click-to-reveal diagram overlays ·
scripted page-range printing · fixed-grid table subtotals/totals · the full
mouse/focus/keystroke event model.

All of these ride on **F1 + F4**. Once those two exist, each individual feature
is **S–M** of helper code. Server-side digital signing (**F7**, **L**) is the
one heavyweight addition and stands alone.

### Tier C — emittable but the ecosystem has abandoned it — **skip**

Multimedia (video / audio — Flash-era) · 3D models (U3D / PRC — Acrobat-only,
deprecated, needs an encoder that doesn't exist in PHP) · XFA dynamic forms
(true add-a-row tables — removed from PDF 2.0, Adobe end-of-life).

---

## Recommended sequence

1. **F3 + F2** (S + S–M) — bookmarks, destination links, named-action and
   URI/reset/submit buttons. Cheap, universal, immediately useful.
2. **F5** (M) — optional-content layers. Universal-ish, high value for
   engineering drawings, no JS.
3. **F1** (L) — AcroForm fields with self-drawn appearance streams. Universal
   fillable/printable/saveable forms. The keystone.
4. **F6** (M) — importer exposes AcroForm; writer rewrites values. Unlocks
   server-side template prefill and data extraction — the most portable
   high-value use case.
5. **F4** (M) — JavaScript plumbing + `Pdf\Interactive\Js` recipes. Opens all
   of Tier B. Ship with a prominent "requires Adobe Reader" convention.
6. **Tier-B helpers** (S–M each) — calculators, conditional forms,
   configurators, quizzes, as demand dictates.
7. **F7** (L, then XL for LTV) — server-side PKCS#7 signing, as its own
   milestone.
