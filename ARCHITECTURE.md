# Architecture: markdown (cebe/markdown)

## Purpose

A fast and extensible Markdown parser for PHP. Supports the original Markdown spec, GitHub Flavored Markdown (GFM), and Markdown Extra. Designed to be composable via PHP traits.

## Directory Structure

```
Parser.php          — Abstract base: core parsing loop, inline/block dispatch, trait composition
Markdown.php        — Standard Markdown (Daring Fireball spec)
MarkdownExtra.php   — Markdown Extra (footnotes, definition lists, fenced code, tables, etc.)
GithubMarkdown.php  — GitHub Flavored Markdown (task lists, tables, strikethrough, auto-links)
block/
  CodeTrait.php       — Indented and fenced code blocks
  HeadlineTrait.php   — ATX and Setext headings
  HtmlTrait.php       — Raw HTML pass-through
  ListTrait.php       — Ordered and unordered lists
  QuoteTrait.php      — Blockquotes
  RuleTrait.php       — Horizontal rules
  ...
inline/
  CodeTrait.php       — Inline code spans
  EmphStrongTrait.php — Emphasis and strong
  LinkTrait.php       — Links and images
  ...
bin/
  markdown            — CLI script for converting Markdown files
```

## Key Design Decisions

- **Trait-based composition**: Block and inline element parsers are implemented as PHP traits. Subclasses select capabilities by `use`-ing traits, and can alias/override individual `identify*` and `consume*` methods to resolve conflicts
- **Two-pass parsing**: Block structure is identified first; inline markup within blocks is parsed in a second pass
- **No regex-heavy dispatch**: The parser indexes blocks by their first character, calling `identifyXxx()` methods only for matching characters, which is faster than a cascade of regexes

## Extension Points

- Implement a new `block\MyTrait` with `identifyXxx()` and `consumeXxx()` methods, then `use` it in a subclass of `Markdown`
- Override `renderXxx()` methods to change HTML output

## Dependency Flow

```
Parser (abstract)
  ├── Markdown  (uses block/* traits + inline/* traits)
  ├── GithubMarkdown  (extends Markdown, adds GFM traits)
  └── MarkdownExtra  (extends Markdown, adds Extra traits)
```
