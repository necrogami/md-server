<?php

namespace MdServer\Model;

final readonly class TreeNode
{
    /** @param TreeNode[] $children */
    public function __construct(
        public string $name,
        public string $path,
        public string $type,
        public string $title,
        public array $children = [],
    ) {}

    public static function file(string $name, string $path, ?string $title = null): self
    {
        return new self(
            name: $name,
            path: $path,
            type: 'file',
            title: $title ?? self::nameToTitle($name),
        );
    }

    /** @param TreeNode[] $children */
    public static function directory(string $name, string $path, array $children = []): self
    {
        return new self(
            name: $name,
            path: $path,
            type: 'directory',
            title: $name,
            children: $children,
        );
    }

    private static function nameToTitle(string $filename): string
    {
        return preg_replace('/\.md$/i', '', $filename);
    }
}
