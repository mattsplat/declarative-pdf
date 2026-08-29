# declarative-pdf — working guidelines

Guidance for AI assistants working in this repo. Modelled on the Laravel AI
guidelines, adapted for a standalone typed PHP library.

## Foundational context

- **What this is:** `mattsplat/declarative-pdf` — a typed, declarative PDF
  generation library with a real block-layout engine. You describe a document
  as an immutable tree of nodes; a `measure → paginate → render → serialise`
  pipeline places everything. It is a from-scratch reimagining of FPDF 1.9; the
  low-level PDF writer, font metrics/embedding and image decoders were ported
  from that release.
- **No framework.** Plain PHP, PSR-4 (`Pdf\` → `src/`), zero runtime
  dependencies beyond `ext-zlib` + `ext-mbstring` (`ext-gd`/`ext-iconv`
  optional). Keeping it dependency-free is a feature — do not add packages
  without asking.
- **Toolchain:** PHP 8.3+ (CI also runs 8.4) · PHPUnit 11 · PHPStan 2 at
  **level 6**. No Pint/CS-Fixer config today — match the surrounding style by
  hand.
- **Docs:** [`docs/`](docs/) — start at [`docs/architecture.md`](docs/architecture.md)
  for the pipeline and [`docs/roadmap.md`](docs/roadmap.md) for what's planned.

## Conventions

- **Follow the conventions already in this codebase.** Before writing anything
  new, read two or three sibling files in the same directory and match their
  structure, naming, and level of comment density.
- **Reuse before adding.** Check for an existing node, box, value object, or
  helper that already does what you need. New public surface area is a
  deliberate decision, not a default.
- Stick to the existing `src/` directory layout (below). Do not reorganise it
  as a side effect of a change.
- **PSR-4 is strict:** one class per file, filename === class name. PHPStan
  will not catch a second class in a file but the autoloader will fail at
  runtime.

### `src/` layout

| Dir | Holds |
|---|---|
| `Node/` | the immutable document tree — `Document`, `Page`, `Paragraph`, `Table`, … (`final readonly`, implement `BlockNode`) |
| `Builder/` | fluent builders that accumulate mutable state and emit the tree on `->build()` |
| `Style/` | `Style`, `StylePatch` (sparse overrides), `StyleResolver`, `Stylesheet`, enums |
| `Layout/` | the engine — `Measurer`, `LineBreaker`, `Paginator`, `TableLayout`, and `Box/*` (the box model: `contentHeightPt`, `split`, `render`, …) |
| `Render/` | `DocumentRenderer` (the pipeline entry) + the byte-level PDF writer, ported from FPDF |
| `Font/` `Image/` `Import/` | font loading/embedding · image decoders · the pure-PHP PDF-page importer |
| `Geometry/` `Color/` `Text/` | value objects, colour, inline text / encoding / inline HTML |
| `Output/` `Support/` `Exception/` | output destinations · `Clock` · the exception hierarchy |

## Non-negotiable invariants

Breaking any of these is a bug even if the tests pass:

1. **All internal measurement is in PostScript points.** User units (mm, cm, in)
   are converted at the API boundary only. Never let a user unit reach the
   layout engine or the writer.
2. **The Y-axis flip happens in exactly one place:** `PageGeometry::flipY()`.
   PDF's origin is bottom-left; everything else in this codebase works
   top-down. Never write `pageHeight - y` anywhere else.
3. **Ported code stays faithful.** Comments citing `fpdf.php:NNN` refer to FPDF
   1.9 (not a file in this repo). When touching `Render/` (writer, xref,
   trailer), `Font/FontWriter`, or the image decoders, preserve the original
   byte-level behaviour — the golden files lock it. Refactor structure, not
   output.
4. **Determinism.** With a `FixedClock`, `compress: false`, and a fixed
   `producer` string, output is byte-stable. The golden tests depend on this.
   Anything that introduces nondeterminism (hash ordering, timestamps, `spl_*`
   ids in output) is a defect.
5. **Nodes are immutable.** `final readonly class`, constructor-only state, no
   setters. Builders are the only mutable layer.

## PHP style

- `declare(strict_types=1);` in every file. `namespace Pdf\…;`.
- **`final` by default.** `readonly` for every value object / node. `abstract`
  only where there is a real hierarchy (e.g. `Layout\Box\AbstractBox`).
- **Constructor property promotion** always. No empty `__construct()`.
- **Explicit return types on every method and function**, including `: void`
  and `: self`. Explicit parameter types always.
- **Curly braces on every control structure**, even one-liners.
- **PHPDoc only where the native type is not precise enough** — array shapes
  (`@param list<Box> $children`, `@return array{0: ?Box, 1: ?Box}`), generics,
  `@phpstan-*`. No `@param`/`@return` that merely restates the signature. No
  author tags, no changelogs.
- **Comments are for *why*, not *what*.** Prefer a descriptive name over a
  comment. Match the (fairly low) comment density of the file you're editing.
  A short class-level docblock explaining the role of the class is welcome.
- **Enums for every closed set**; cases in `TitleCase` (`TextAlign::Justify`,
  `FontStyle::BoldItalic`).
- Keep methods small and single-purpose; the line breaker and paginator are the
  hot paths — be mindful of allocations in loops there.

## Testing

- **Write tests as you go.** New layout behaviour needs a unit test; new
  rendered output needs a functional test and usually a golden file.
- `tests/Unit/` — pure logic (`TableLayout`, `LineBreaker`, `StyleResolver`,
  `FontMetrics`). `tests/Functional/` — full render + `Golden::assert()` +
  `pdftotext`/structure assertions.
- Test classes: `final class XxxTest extends PHPUnit\Framework\TestCase`,
  `declare(strict_types=1)`, methods named `test_snake_case_describes_behaviour`
  (no `@test`, no `testCamelCase`).
- Use the support helpers: `Pdf\Tests\Support\Pdf::deterministicRenderer()`,
  `::contentText()`, `Golden::assert()`, `Fonts`, `FakeBox`.
- **Golden files** (`tests/golden/*.pdf`) are committed reference data. When an
  intended change shifts output, regenerate with `UPDATE_GOLDENS=1 vendor/bin/phpunit`
  and then **verify the new bytes are actually correct** — run `pdftotext -layout`
  and `qpdf --check` on the result and confirm the text/layout is right. A
  golden diff is never "just accept it"; it's "prove the new output is what you
  intended." Small (~4-byte) diffs are usually float-rounding shifts; large
  diffs need scrutiny.
- `phpunit.xml` sets `failOnWarning` and `failOnNotice` — deprecations and
  risky tests fail the suite.

## Static analysis

- `vendor/bin/phpstan analyse` must be **clean at level 6** before you consider
  a change done. Do not lower the level, add broad `ignoreErrors`, or sprinkle
  `@phpstan-ignore` to get past a real finding — fix the type. Narrow,
  commented `@phpstan-ignore-next-line` for a genuine false positive is
  acceptable.

## Verification

Run these — do not write throwaway verification scripts:

```
composer test                      # vendor/bin/phpunit  — 201 tests
composer stan                      # vendor/bin/phpstan analyse  — level 6
for f in examples/*.php; do php "$f"; done   # render all 11 examples
UPDATE_GOLDENS=1 composer test     # regenerate goldens after an intended change
```

CI (`.github/workflows/ci.yml`) runs PHPStan + PHPUnit on PHP 8.3 and 8.4, plus
a structural job that renders every example and runs `qpdf --check` /
`pdftotext` on the output. A change is not done until all of that would pass.

## Dependencies

- **Do not add a runtime dependency without explicit approval.** Zero-deps is a
  design goal. `require-dev` additions (a new dev tool) also need a heads-up.
- Do not bump the minimum PHP version or drop an extension from `suggest`
  without asking.

## Documentation files

- **Only create or edit files in `docs/` when explicitly asked.** Do not
  proactively write summary docs, migration notes, or "here's what I did" files.
- When asked: keep `docs/` in the existing house style (concise, tables over
  prose, compile-tested code snippets), and add a one-line entry to
  [`docs/README.md`](docs/README.md).
- Code snippets in docs must actually run against the current API — check them.

## Replies

- Be concise. Lead with the answer or the change; keep rationale to what the
  reader needs. Reference code as `path/to/file.php:line`.
- When a design decision is genuinely the user's to make (a public API shape, a
  trade-off with no clear default), ask — don't guess and build.

## Commits & PRs

- This repo uses plain git. Branch before committing if on `main`. Commit or
  push only when asked.
- Commit messages: imperative mood, explain the *why* for anything non-obvious.
- End commit messages with:
  `Co-Authored-By: Claude Sonnet 5 <noreply@anthropic.com>`
