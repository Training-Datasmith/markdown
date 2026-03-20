<?php

declare(strict_types=1);

require_once __DIR__ . '/../vendor/autoload.php';

use cebe\markdown\Markdown;
use cebe\markdown\GithubMarkdown;
use cebe\markdown\MarkdownExtra;

// --- Example 1: Standard Markdown ---
$parser = new Markdown();

$markdown = '# Hello World

This is a **bold** statement and this is *italic*.

- Item one
- Item two
- Item three

[Visit example.com](https://example.com)
';

echo $parser->parse($markdown);
echo "\n";

// --- Example 2: GitHub Flavored Markdown (GFM) ---
$gfm_parser = new GithubMarkdown();
$gfm_parser->html5 = true;

$gfm = '## Task List

- [x] Write tests
- [ ] Deploy to production
- [x] Review PR

~~Strikethrough text~~

| Name  | Score |
|-------|-------|
| Alice |   95  |
| Bob   |   82  |
';

echo $gfm_parser->parse($gfm);
echo "\n";

// --- Example 3: Markdown Extra (footnotes, definition lists, fenced code) ---
$extra_parser = new MarkdownExtra();

$extra = '```php
echo "Hello, World!";
```

Apple
:   A fruit that is red or green.

Orange
:   A citrus fruit.
';

echo $extra_parser->parse($extra);
echo "\n";

// --- Example 4: Inline parsing (for a single paragraph, no wrapping <p>) ---
$inline_parser = new Markdown();
echo $inline_parser->parseParagraph('This has **bold** and `code` inline.');
echo "\n";
