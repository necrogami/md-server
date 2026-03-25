<?php

use MdServer\Service\FileTreeBuilder;

function rmdir_recursive(string $dir): void
{
    if (!is_dir($dir)) return;
    $items = scandir($dir);
    foreach ($items as $item) {
        if ($item === '.' || $item === '..') continue;
        $path = $dir . '/' . $item;
        if (is_dir($path)) {
            rmdir_recursive($path);
        } else {
            unlink($path);
        }
    }
    rmdir($dir);
}

$tempDir = '';

beforeEach(function () use (&$tempDir) {
    $tempDir = sys_get_temp_dir() . '/filetreebuilder_test_' . uniqid();
    mkdir($tempDir, 0755, true);
});

afterEach(function () use (&$tempDir) {
    rmdir_recursive($tempDir);
});

test('builds tree from flat directory in alphabetical order', function () use (&$tempDir) {
    file_put_contents($tempDir . '/zebra.md', '# Zebra');
    file_put_contents($tempDir . '/apple.md', '# Apple');
    file_put_contents($tempDir . '/mango.md', '# Mango');

    $builder = new FileTreeBuilder();
    $tree = $builder->build($tempDir);

    expect($tree)->toHaveCount(3);
    expect($tree[0]->name)->toBe('apple.md');
    expect($tree[1]->name)->toBe('mango.md');
    expect($tree[2]->name)->toBe('zebra.md');
});

test('builds nested tree with directories before files', function () use (&$tempDir) {
    mkdir($tempDir . '/subdir', 0755);
    file_put_contents($tempDir . '/subdir/child.md', '# Child');
    file_put_contents($tempDir . '/root.md', '# Root');

    $builder = new FileTreeBuilder();
    $tree = $builder->build($tempDir);

    expect($tree)->toHaveCount(2);
    expect($tree[0]->type)->toBe('directory');
    expect($tree[0]->name)->toBe('subdir');
    expect($tree[0]->children)->toHaveCount(1);
    expect($tree[0]->children[0]->name)->toBe('child.md');
    expect($tree[1]->type)->toBe('file');
    expect($tree[1]->name)->toBe('root.md');
});

test('ignores directory patterns ending with slash', function () use (&$tempDir) {
    mkdir($tempDir . '/vendor', 0755);
    file_put_contents($tempDir . '/vendor/package.md', '# Package');
    file_put_contents($tempDir . '/kept.md', '# Kept');

    $builder = new FileTreeBuilder();
    $tree = $builder->build($tempDir, ['vendor/']);

    expect($tree)->toHaveCount(1);
    expect($tree[0]->name)->toBe('kept.md');
});

test('ignores glob patterns like *.log', function () use (&$tempDir) {
    file_put_contents($tempDir . '/notes.md', '# Notes');
    file_put_contents($tempDir . '/debug.log', 'log data');

    $builder = new FileTreeBuilder();
    $tree = $builder->build($tempDir, ['*.log']);

    expect($tree)->toHaveCount(1);
    expect($tree[0]->name)->toBe('notes.md');
});

test('only includes .md files', function () use (&$tempDir) {
    file_put_contents($tempDir . '/doc.md', '# Doc');
    file_put_contents($tempDir . '/image.png', 'binary');
    file_put_contents($tempDir . '/readme.txt', 'text');

    $builder = new FileTreeBuilder();
    $tree = $builder->build($tempDir);

    expect($tree)->toHaveCount(1);
    expect($tree[0]->name)->toBe('doc.md');
});

test('extracts title from YAML frontmatter', function () use (&$tempDir) {
    $content = "---\ntitle: My Document Title\nauthor: Someone\n---\n\n# Content here\n";
    file_put_contents($tempDir . '/doc.md', $content);

    $builder = new FileTreeBuilder();
    $tree = $builder->build($tempDir);

    expect($tree)->toHaveCount(1);
    expect($tree[0]->title)->toBe('My Document Title');
});

test('falls back to filename without .md when no frontmatter', function () use (&$tempDir) {
    file_put_contents($tempDir . '/my-document.md', '# Some content without frontmatter');

    $builder = new FileTreeBuilder();
    $tree = $builder->build($tempDir);

    expect($tree)->toHaveCount(1);
    expect($tree[0]->title)->toBe('my-document');
});

test('excludes empty directories', function () use (&$tempDir) {
    mkdir($tempDir . '/empty-dir', 0755);
    mkdir($tempDir . '/non-empty-dir', 0755);
    file_put_contents($tempDir . '/non-empty-dir/page.md', '# Page');

    $builder = new FileTreeBuilder();
    $tree = $builder->build($tempDir);

    expect($tree)->toHaveCount(1);
    expect($tree[0]->name)->toBe('non-empty-dir');
    expect($tree[0]->type)->toBe('directory');
});
