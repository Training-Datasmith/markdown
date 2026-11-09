# Architecture: markdown (cebe/markdown)

## Purpose

A fast and extensible Markdown parser for PHP. Supports the original Markdown spec, GitHub Flavored Markdown (GFM), and Markdown Extra. Designed to be composable via PHP traits.

## Directory Structure

```
Parser.php          — Abstract base: core parsing loop, inline/block dispatch, trait composition
Markdown.php        — Standard Markdown (Daring Fireball spec)
Markdown_Extra.php   — Markdown Extra (footnotes, definition lists, fenced code, tables, etc.)
Github_Markdown.php  — GitHub Flavored Markdown (task lists, tables, strikethrough, auto-links)
block/
  Code_Trait.php       — Indented and fenced code blocks
  Headline_Trait.php   — ATX and Setext headings
  Html_Trait.php       — Raw HTML pass-through
  List_Trait.php       — Ordered and unordered lists
  Quote_Trait.php      — Blockquotes
  Rule_Trait.php       — Horizontal rules
  ...
inline/
  Code_Trait.php       — Inline code spans
  Emph_Strong_Trait.php — Emphasis and strong
  Link_Trait.php       — Links and images
  ...
bin/
  markdown            — CLI script for converting Markdown files
```

## Key Design Decisions

- **Trait-based composition**: Block and inline element parsers are implemented as PHP traits. Subclasses select capabilities by `use`-ing traits, and can alias/override individual `identify*` and `consume*` methods to resolve conflicts
- **Two-pass parsing**: Block structure is identified first; inline markup within blocks is parsed in a second pass
- **No regex-heavy dispatch**: The parser indexes blocks by their first character, calling `identify_xxx()` methods only for matching characters, which is faster than a cascade of regexes

## Extension Points

- Implement a new `block\MyTrait` with `identify_xxx()` and `consume_xxx()` methods, then `use` it in a subclass of `Markdown`
- Override `render_xxx()` methods to change HTML output

## Dependency Flow

```
Parser (abstract)
  ├── Markdown  (uses block/* traits + inline/* traits)
  ├── Github_Markdown  (extends Markdown, adds GFM traits)
  └── Markdown_Extra  (extends Markdown, adds Extra traits)
```
