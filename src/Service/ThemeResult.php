<?php
namespace MdServer\Service;

final readonly class ThemeResult
{
    /** @param string[] $availableThemes @param array<string, string> $themePaths */
    public function __construct(
        public string $activeMode,
        public array $availableThemes,
        public array $themePaths,
        public bool $hasCustomThemes,
        public ?string $overrideCssPath,
    ) {}
}
