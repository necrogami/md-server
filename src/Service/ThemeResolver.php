<?php
namespace MdServer\Service;

class ThemeResolver
{
    public function __construct(
        private readonly string $servingRoot,
        private readonly ?string $globalConfigDir = null,
    ) {}

    public function resolve(string $mode): ThemeResult
    {
        $themes = ['light' => 'built-in', 'dark' => 'built-in'];
        $themePaths = [];

        // Tier 2: Global shared themes
        $globalThemesDir = $this->getGlobalThemesDir();
        if ($globalThemesDir !== null && is_dir($globalThemesDir)) {
            foreach ($this->findCssFiles($globalThemesDir) as $name => $path) {
                $themes[$name] = 'global';
                $themePaths[$name] = $path;
            }
        }

        // Tier 4: Project theme directory (overrides global with same name)
        $projectThemesDir = $this->servingRoot . '/.themes';
        if (is_dir($projectThemesDir)) {
            foreach ($this->findCssFiles($projectThemesDir) as $name => $path) {
                $themes[$name] = 'project';
                $themePaths[$name] = $path;
            }
        }

        // Tier 3: Project override file
        $overridePath = null;
        $overrideFile = $this->servingRoot . '/.md-theme.css';
        if (is_file($overrideFile)) {
            $overridePath = $overrideFile;
        }

        $hasSelectableCustom = count($themes) > 2;

        return new ThemeResult(
            activeMode: $mode,
            availableThemes: array_keys($themes),
            themePaths: $themePaths,
            hasCustomThemes: $hasSelectableCustom,
            overrideCssPath: $overridePath,
        );
    }

    private function getGlobalThemesDir(): ?string
    {
        if ($this->globalConfigDir !== null) {
            return $this->globalConfigDir . '/themes';
        }
        return null;
    }

    /** @return array<string, string> name => full path */
    private function findCssFiles(string $dir): array
    {
        $result = [];
        $files = scandir($dir);
        if ($files === false) return [];

        foreach ($files as $file) {
            if (str_ends_with($file, '.css')) {
                $result[basename($file, '.css')] = $dir . '/' . $file;
            }
        }
        return $result;
    }
}
