# Contributing

Thanks for taking the time to contribute.

## Getting set up

```bash
git clone https://github.com/mattsplat/declarative-pdf
cd declarative-pdf
composer install
composer check          # phpstan (level 6) + phpunit
```

The example renders in CI also need `qpdf` and `poppler-utils` (`pdftotext`);
they are optional locally.

## Before opening a pull request

- **`composer stan` is clean at level 6.** Do not lower the level or add broad
  `ignoreErrors` — fix the type. A narrow, commented `@phpstan-ignore-next-line`
  for a genuine false positive is fine.
- **`composer test` passes.** `phpunit.xml` sets `failOnWarning` and
  `failOnNotice`, so deprecations fail the suite.
- **New behaviour has a test.** New layout logic → a unit test in `tests/Unit/`;
  new rendered output → a functional test in `tests/Functional/`, usually with a
  golden file.
- **Golden files are reference data.** If an intended change shifts output,
  regenerate with `UPDATE_GOLDENS=1 composer test`, then *prove the new bytes
  are correct* — run `pdftotext -layout` and `qpdf --check` on the result and
  confirm the text and layout are what you meant. A golden diff is never "just
  accept it".
- **The examples still render.** `for f in examples/*.php; do php "$f"; done`.

## Style

There is no automatic formatter yet — match the surrounding code. In short:
`declare(strict_types=1)` everywhere, `final` and `readonly` by default,
constructor property promotion, explicit parameter and return types (including
`: void` and `: self`), curly braces on every control structure, enums for
closed sets. Comments explain *why*, not *what*.

See [`CLAUDE.md`](CLAUDE.md) for the full house rules and the invariants that
must not be broken (points-only internally, the single Y-axis flip,
byte-for-byte determinism, immutable nodes).

## Commits

Imperative mood; explain the *why* for anything non-obvious. Branch before
committing if you are on `master`.
