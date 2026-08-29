# Documentation

- **[Getting started](getting-started.md)** — install, hello world, the two
  construction styles, output destinations, determinism.
- **[Cookbook](cookbook.md)** — task-oriented recipes: headers/footers, tables,
  pagination, lists, columns, inline formatting, internal links, bookmarks,
  images, large-format sheets, text/block measurement, reusable components,
  PDF import, custom fonts, house styles with named class rules.
- **[API reference](reference.md)** — every builder method, node constructor,
  style option, enum, and the `Pdf\Import\*` reader API.
- **[FPDF vs. declarative — side by side](fpdf-vs-declarative.md)** — the seven
  FPDF tutorials, each next to its declarative rewrite.
- **[Porting from FPDF](porting.md)** — `Cell` / `MultiCell` / `AddPage` /
  `Header()` / `WriteHTML` / `AddFont` → the declarative equivalent, in prose.
- **[Comparison: FPDF / TCPDF / tc-lib-pdf / PDFBlocks](comparison.md)** — feature
  matrix, where this library wins and loses, and which to reach for.
- **[PDFBlocks vs. this library](pdfblocks-vs-declarative.md)** — the Swift
  SwiftUI-style PDF library, construct-by-construct syntax side by side.
- **[Architecture](architecture.md)** — the measure → paginate → render
  pipeline, the box model, and what was ported vs. rewritten.
- **[Roadmap](roadmap.md)** — potential features, sized and prioritised
  (forms, JavaScript, drawing, subsetting, tagged PDF, native merge, …).
- **[Interactive PDF feasibility](interactive-pdf-feasibility.md)** — the
  forms / JS / layers / signatures / 3D wishlist analysed for difficulty and,
  crucially, for which viewers the behaviour actually reaches.
