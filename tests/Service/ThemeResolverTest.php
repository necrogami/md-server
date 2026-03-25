<?php

use MdServer\Service\ThemeResolver;

beforeEach(function () {
    $this->tempDir = sys_get_temp_dir() . '/md-theme-test-' . uniqid();
    mkdir($this->tempDir, 0755, true);
});

afterEach(function () {
    rmdir_recursive($this->tempDir);
});

test('returns built-in themes only when no custom themes exist', function () {
    $resolver = new ThemeResolver($this->tempDir);
    $result = $resolver->resolve('light');

    expect($result->availableThemes)->toBe(['light', 'dark']);
    expect($result->hasCustomThemes)->toBeFalse();
    expect($result->overrideCssPath)->toBeNull();
    expect($result->themePaths)->toBe([]);
});

test('detects project override CSS file', function () {
    $overridePath = $this->tempDir . '/.md-theme.css';
    file_put_contents($overridePath, 'body { color: red; }');

    $resolver = new ThemeResolver($this->tempDir);
    $result = $resolver->resolve('light');

    expect($result->overrideCssPath)->toBe($overridePath);
    expect($result->hasCustomThemes)->toBeFalse();
});

test('detects project theme directory with custom themes', function () {
    $themesDir = $this->tempDir . '/.themes';
    mkdir($themesDir, 0755, true);
    file_put_contents($themesDir . '/solarized.css', '.solarized {}');
    file_put_contents($themesDir . '/monokai.css', '.monokai {}');

    $resolver = new ThemeResolver($this->tempDir);
    $result = $resolver->resolve('light');

    expect($result->hasCustomThemes)->toBeTrue();
    expect($result->availableThemes)->toContain('light');
    expect($result->availableThemes)->toContain('dark');
    expect($result->availableThemes)->toContain('solarized');
    expect($result->availableThemes)->toContain('monokai');
    expect($result->themePaths)->toHaveKey('solarized');
    expect($result->themePaths)->toHaveKey('monokai');
});

test('detects global themes', function () {
    $globalConfigDir = $this->tempDir . '/global-config';
    $globalThemesDir = $globalConfigDir . '/themes';
    mkdir($globalThemesDir, 0755, true);
    file_put_contents($globalThemesDir . '/corporate.css', '.corporate {}');

    $resolver = new ThemeResolver($this->tempDir, $globalConfigDir);
    $result = $resolver->resolve('light');

    expect($result->hasCustomThemes)->toBeTrue();
    expect($result->availableThemes)->toContain('corporate');
    expect($result->themePaths)->toHaveKey('corporate');
    expect($result->themePaths['corporate'])->toBe($globalThemesDir . '/corporate.css');
});

test('project theme overrides global theme with same name', function () {
    $globalConfigDir = $this->tempDir . '/global-config';
    $globalThemesDir = $globalConfigDir . '/themes';
    mkdir($globalThemesDir, 0755, true);
    file_put_contents($globalThemesDir . '/custom.css', '.global-custom {}');

    $projectThemesDir = $this->tempDir . '/.themes';
    mkdir($projectThemesDir, 0755, true);
    file_put_contents($projectThemesDir . '/custom.css', '.project-custom {}');

    $resolver = new ThemeResolver($this->tempDir, $globalConfigDir);
    $result = $resolver->resolve('light');

    $customCount = array_count_values($result->availableThemes)['custom'];
    expect($customCount)->toBe(1);
    expect($result->themePaths['custom'])->toBe($projectThemesDir . '/custom.css');
});

test('auto mode is passed through as active mode', function () {
    $resolver = new ThemeResolver($this->tempDir);
    $result = $resolver->resolve('auto');

    expect($result->activeMode)->toBe('auto');
});

test('explicit dark mode selection is preserved', function () {
    $resolver = new ThemeResolver($this->tempDir);
    $result = $resolver->resolve('dark');

    expect($result->activeMode)->toBe('dark');
});
