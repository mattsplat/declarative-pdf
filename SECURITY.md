# Security Policy

## Supported versions

While the project is `0.x`, only the latest release on `master` receives
security fixes.

## Reporting a vulnerability

Please **do not open a public issue** for a security problem.

Report it privately through GitHub:
[**Report a vulnerability**](https://github.com/mattsplat/declarative-pdf/security/advisories/new)
(Security → Advisories → Report a vulnerability).

If you cannot use GitHub advisories, email matthewjohncoleman@gmail.com with
"declarative-pdf security" in the subject.

Please include a description of the issue, the affected version or commit, and a
minimal reproduction if you have one. You will get an acknowledgement within a
few days.

## Scope

The areas most worth scrutiny:

- **PDF import** (`Pdf\Import\*`) — parses untrusted-looking byte streams.
  Encrypted input is rejected; malformed input should raise `PdfException`, not
  crash or hang.
- **Image decoding** (`Pdf\Image\*`) — decodes JPEG/PNG/GIF/WebP bytes.
- **Image fetching** (`Pdf\Image\ImageFactory`) — follows `http(s)://` URLs and
  `data:` URIs. There is **no SSRF guard**; treat image URLs as trusted input
  and do not pass user-supplied URLs without your own allow-listing.
- **Document JavaScript** (`Pdf\Interactive\Js`) — embeds author-supplied
  JavaScript into the PDF. It is inert unless the reader is Acrobat/Reader; it
  is never executed at generation time.

Denial-of-service through pathological layout input (unbounded pagination,
enormous tables) is in scope.
