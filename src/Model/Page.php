<?php

namespace MdServer\Model;

final readonly class Page
{
    /** @param string[] $breadcrumbs */
    public function __construct(
        public string $path,
        public string $title,
        public string $rawMarkdown,
        public string $renderedHtml,
        public array $frontmatter,
        public array $breadcrumbs,
    ) {}

    public static function fromFile(
        string $path,
        string $rawMarkdown,
        string $renderedHtml,
        array $frontmatter,
    ): self {
        $title = $frontmatter['title']
            ?? preg_replace('/\.md$/i', '', basename($path));

        $segments = explode('/', trim($path, '/'));
        $breadcrumbs = array_map(
            fn (string $s) => preg_replace('/\.md$/i', '', $s),
            $segments,
        );

        return new self(
            path: $path,
            title: $title,
            rawMarkdown: $rawMarkdown,
            renderedHtml: $renderedHtml,
            frontmatter: $frontmatter,
            breadcrumbs: $breadcrumbs,
        );
    }
}
