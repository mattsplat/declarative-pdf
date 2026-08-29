<!-- Thanks for contributing! Keep this short. -->

## What & why

<!-- What does this change and what problem does it solve? -->

## Checklist

- [ ] `composer stan` is clean at level 6
- [ ] `composer test` passes
- [ ] New behaviour is covered by a test
- [ ] If output changed: goldens regenerated **and** verified with `pdftotext -layout` + `qpdf --check`
- [ ] `for f in examples/*.php; do php "$f"; done` still renders
- [ ] `CHANGELOG.md` updated under **Unreleased** (for user-facing changes)
