<?php

namespace MdServer\Service;

use MdServer\Model\Config;
use Symfony\Component\Yaml\Exception\ParseException;
use Symfony\Component\Yaml\Yaml;

class ConfigLoader
{
    public function __construct(
        private readonly string $servingRoot,
        private readonly ?string $globalConfigDir = null,
    ) {}

    /** @param array<string, mixed> $cliOverrides */
    public function load(array $cliOverrides = []): Config
    {
        $config = [];

        // Tier 1: Global config
        $globalConfig = $this->loadGlobalConfig();
        if ($globalConfig !== null) {
            $config = $globalConfig;
        }

        // Tier 2: Project .mdrc
        $projectConfig = $this->loadProjectConfig();
        if ($projectConfig !== null) {
            $config = $this->deepMerge($config, $projectConfig);
        }

        // Tier 3: Env var overrides (from ServeCommand)
        $envOverrides = $this->loadEnvOverrides();
        if ($envOverrides !== []) {
            $config = $this->deepMerge($config, $envOverrides);
        }

        // Tier 3: Direct CLI overrides (highest priority)
        if ($cliOverrides !== []) {
            $config = $this->deepMerge($config, $cliOverrides);
        }

        return Config::fromArray($config, $this->servingRoot);
    }

    private function loadEnvOverrides(): array
    {
        $overrides = [];

        $theme = getenv('MD_SERVER_THEME');
        if ($theme !== false && $theme !== '') {
            $overrides['theme'] = ['mode' => $theme];
        }

        $noTree = getenv('MD_SERVER_NO_TREE');
        if ($noTree === '1') {
            $overrides['navigation'] = ['show_tree' => false];
        }

        return $overrides;
    }

    private function loadGlobalConfig(): ?array
    {
        $dir = $this->globalConfigDir ?? $this->detectGlobalConfigDir();
        if ($dir === null) {
            return null;
        }

        return $this->loadYamlFile($dir . '/config.yaml');
    }

    private function loadProjectConfig(): ?array
    {
        return $this->loadYamlFile($this->servingRoot . '/.mdrc');
    }

    private function loadYamlFile(string $path): ?array
    {
        if (!is_file($path) || !is_readable($path)) {
            return null;
        }

        try {
            $data = Yaml::parseFile($path);
            return is_array($data) ? $data : null;
        } catch (ParseException) {
            return null;
        }
    }

    private function detectGlobalConfigDir(): ?string
    {
        $xdg = getenv('XDG_CONFIG_HOME');
        if ($xdg !== false && $xdg !== '') {
            return $xdg . '/md-server';
        }

        $home = getenv('HOME');
        if ($home !== false && $home !== '') {
            if (PHP_OS_FAMILY === 'Darwin') {
                return $home . '/Library/Application Support/md-server';
            }
            return $home . '/.config/md-server';
        }

        $appdata = getenv('APPDATA');
        if ($appdata !== false && $appdata !== '') {
            return $appdata . '/md-server';
        }

        return null;
    }

    private function deepMerge(array $base, array $overrides): array
    {
        $result = $base;
        foreach ($overrides as $key => $value) {
            if (is_array($value) && isset($result[$key]) && is_array($result[$key])
                && !array_is_list($value)) {
                $result[$key] = $this->deepMerge($result[$key], $value);
            } else {
                $result[$key] = $value;
            }
        }
        return $result;
    }
}
