# Documentation

- **[Getting started](getting-started.md)** — install, hello world, the two
  construction styles, output destinations, determinism.
- **[Cookbook](cookbook.md)** — task-oriented recipes: headers/footers, tables,
  pagination, lists, columns, inline formatting, internal links, images,
  large-format sheets, PDF import, custom fonts, house styles.
- **[API reference](reference.md)** — every builder method, node constructor,
  style option, enum, and the `Pdf\Import\*` reader API.
- **[FPDF vs. declarative — side by side](fpdf-vs-declarative.md)** — the seven
  FPDF tutorials, each next to its declarative rewrite.
- **[Porting from FPDF](porting.md)** — `Cell` / `MultiCell` / `AddPage` /
  `Header()` / `WriteHTML` / `AddFont` → the declarative equivalent, in prose.
- **[Architecture](architecture.md)** — the measure → paginate → render
  pipeline, the box model, and what was ported vs. rewritten.
