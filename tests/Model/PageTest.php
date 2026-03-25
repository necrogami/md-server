<?php

use MdServer\Model\Page;

test('page construction', function () {
    $page = new Page(
        path: 'docs/guide.md',
        title: 'Getting Started',
        rawMarkdown: '# Hello',
        renderedHtml: '<h1>Hello</h1>',
        frontmatter: ['title' => 'Getting Started'],
        breadcrumbs: ['docs', 'guide'],
    );

    expect($page->path)->toBe('docs/guide.md');
    expect($page->title)->toBe('Getting Started');
    expect($page->renderedHtml)->toBe('<h1>Hello</h1>');
    expect($page->breadcrumbs)->toBe(['docs', 'guide']);
});

test('title falls back to filename', function () {
    $page = Page::fromFile('docs/guide.md', '# Hello', '<h1>Hello</h1>', []);

    expect($page->title)->toBe('guide');
});

test('title from frontmatter', function () {
    $page = Page::fromFile('docs/guide.md', '# Hello', '<h1>Hello</h1>', ['title' => 'My Guide']);

    expect($page->title)->toBe('My Guide');
});

test('breadcrumbs from path', function () {
    $page = Page::fromFile('docs/api/endpoints.md', '# API', '<h1>API</h1>', []);

    expect($page->breadcrumbs)->toBe(['docs', 'api', 'endpoints']);
});
