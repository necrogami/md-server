<?php

namespace MdServer\Model;

final readonly class Config
{
    public function __construct(
        public string $host,
        public int $port,
        public string $themeMode,
        public ?string $customThemeFile,
        public ?string $customThemeDir,
        public bool $showTree,
        public array $ignorePatterns,
        public array $extensions,
        public string $root,
    ) {}

    public static function fromArray(array $data, string $root = '.'): self
    {
        $defaults = [
            'server' => ['host' => '127.0.0.1', 'port' => 8080],
            'markdown' => ['extensions' => [
                'gfm' => true,
                'frontmatter' => true,
                'mermaid' => true,
                'math' => true,
                'syntax_highlight' => true,
            ]],
            'navigation' => [
                'show_tree' => true,
                'ignore' => ['vendor/', 'node_modules/', '.git/', '*.log'],
            ],
            'theme' => ['mode' => 'auto', 'custom_file' => null, 'custom_dir' => null],
        ];

        $merged = self::deepMerge($defaults, $data);

        return new self(
            host: $merged['server']['host'],
            port: (int) $merged['server']['port'],
            themeMode: $merged['theme']['mode'],
            customThemeFile: $merged['theme']['custom_file'],
            customThemeDir: $merged['theme']['custom_dir'],
            showTree: (bool) $merged['navigation']['show_tree'],
            ignorePatterns: $merged['navigation']['ignore'],
            extensions: $merged['markdown']['extensions'],
            root: $root,
        );
    }

    public function isReadonly(): bool
    {
        return true;
    }

    private static function deepMerge(array $defaults, array $overrides): array
    {
        $result = $defaults;
        foreach ($overrides as $key => $value) {
            if (is_array($value) && isset($result[$key]) && is_array($result[$key])
                && !array_is_list($value)) {
                $result[$key] = self::deepMerge($result[$key], $value);
            } else {
                $result[$key] = $value;
            }
        }
        return $result;
    }
}
