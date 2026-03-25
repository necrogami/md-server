<?php

use MdServer\Service\MarkdownRenderer;

$renderer = new MarkdownRenderer([
    'gfm' => true, 'frontmatter' => true,
    'mermaid' => true, 'math' => true, 'syntax_highlight' => true,
]);

test('renders basic markdown h1', function () use ($renderer) {
    $result = $renderer->render('# Hello World');
    expect($result->html)->toContain('<h1>Hello World</h1>');
});

test('extracts frontmatter title and tags', function () use ($renderer) {
    $markdown = "---\ntitle: My Page\ntags: [php, symfony]\n---\n# Content";
    $result = $renderer->render($markdown);
    expect($result->frontmatter['title'])->toBe('My Page');
    expect($result->frontmatter['tags'])->toBe(['php', 'symfony']);
    expect($result->html)->toContain('<h1>Content</h1>');
});

test('renders GFM tables', function () use ($renderer) {
    $markdown = "| Name | Age |\n|------|-----|\n| Alice | 30 |";
    $result = $renderer->render($markdown);
    expect($result->html)->toContain('<table>');
    expect($result->html)->toContain('<th>Name</th>');
    expect($result->html)->toContain('<td>Alice</td>');
});

test('renders task lists', function () use ($renderer) {
    $markdown = "- [x] Done\n- [ ] Todo";
    $result = $renderer->render($markdown);
    expect($result->html)->toContain('checked');
    expect($result->html)->toContain('type="checkbox"');
});

test('renders fenced code blocks with language class', function () use ($renderer) {
    $markdown = "```php\necho 'hello';\n```";
    $result = $renderer->render($markdown);
    expect($result->html)->toContain('language-php');
});

test('renders mermaid blocks with mermaid class', function () use ($renderer) {
    $markdown = "```mermaid\ngraph TD\n    A --> B\n```";
    $result = $renderer->render($markdown);
    expect($result->html)->toContain('class="mermaid"');
    expect($result->html)->toContain('<pre class="mermaid">');
});

test('renders block math with katex-block class', function () use ($renderer) {
    $markdown = "\$\$\nE = mc^2\n\$\$";
    $result = $renderer->render($markdown);
    expect($result->html)->toContain('class="katex-block"');
    expect($result->html)->toContain('E = mc^2');
});

test('rewrites internal .md links to clean URLs', function () use ($renderer) {
    $markdown = '[Install](install.md)';
    $result = $renderer->render($markdown);
    expect($result->html)->toContain('href="/install"');
    expect($result->html)->not->toContain('.md"');
});

test('preserves external links with target="_blank"', function () use ($renderer) {
    $markdown = '[Example](https://example.com)';
    $result = $renderer->render($markdown);
    expect($result->html)->toContain('target="_blank"');
    expect($result->html)->toContain('rel="noopener noreferrer"');
    expect($result->html)->toContain('href="https://example.com"');
});

test('rewrites nested .md links to clean URLs', function () use ($renderer) {
    $markdown = '[Install](./docs/install.md)';
    $result = $renderer->render($markdown);
    expect($result->html)->toContain('href="/docs/install"');
});
