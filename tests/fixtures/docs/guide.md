---
title: Getting Started
---

# Getting Started

## Features

| Feature | Status |
|---------|--------|
| GFM tables | Supported |
| Task lists | Supported |
| Mermaid | Supported |
| KaTeX math | Supported |

## Task List

- [x] Set up project
- [x] Create renderer
- [ ] Build PHAR

## Code Example

```php
$renderer = new MarkdownRenderer();
$result = $renderer->render('# Hello');
echo $result->html;
```
