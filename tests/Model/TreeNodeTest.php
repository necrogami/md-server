<?php

use MdServer\Model\TreeNode;

test('file node', function () {
    $node = TreeNode::file('guide.md', 'docs/guide.md', 'Getting Started');

    expect($node->name)->toBe('guide.md');
    expect($node->path)->toBe('docs/guide.md');
    expect($node->type)->toBe('file');
    expect($node->title)->toBe('Getting Started');
    expect($node->children)->toBe([]);
});

test('directory node', function () {
    $child = TreeNode::file('readme.md', 'docs/readme.md', 'Readme');
    $dir = TreeNode::directory('docs', 'docs', [$child]);

    expect($dir->type)->toBe('directory');
    expect($dir->children)->toHaveCount(1);
    expect($dir->title)->toBe('docs');
});

test('file title falls back to name without .md', function () {
    $node = TreeNode::file('guide.md', 'guide.md');

    expect($node->title)->toBe('guide');
});
