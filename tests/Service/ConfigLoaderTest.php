<?php

use MdServer\Service\ConfigLoader;

beforeEach(function () {
    $this->tempDir = sys_get_temp_dir() . '/md-server-test-' . uniqid();
    mkdir($this->tempDir, 0755, true);
});

afterEach(function () {
    rmdir_recursive($this->tempDir);
});


test('loads defaults when no config files exist', function () {
    $loader = new ConfigLoader($this->tempDir);
    $config = $loader->load();

    expect($config->host)->toBe('127.0.0.1');
    expect($config->port)->toBe(8080);
});

test('loads .mdrc from serving root', function () {
    file_put_contents($this->tempDir . '/.mdrc', "server:\n  port: 3000\n");

    $loader = new ConfigLoader($this->tempDir);
    $config = $loader->load();

    expect($config->port)->toBe(3000);
    expect($config->host)->toBe('127.0.0.1'); // default preserved
});

test('CLI overrides .mdrc', function () {
    file_put_contents($this->tempDir . '/.mdrc', "server:\n  port: 3000\n");

    $loader = new ConfigLoader($this->tempDir);
    $config = $loader->load(['server' => ['port' => 9000]]);

    expect($config->port)->toBe(9000);
});

test('loads global config', function () {
    $globalDir = $this->tempDir . '/global-config';
    mkdir($globalDir, 0755, true);
    file_put_contents($globalDir . '/config.yaml', "theme:\n  mode: dark\n");

    $loader = new ConfigLoader($this->tempDir, $globalDir);
    $config = $loader->load();

    expect($config->themeMode)->toBe('dark');
});

test('project overrides global', function () {
    $globalDir = $this->tempDir . '/global-config';
    mkdir($globalDir, 0755, true);
    file_put_contents($globalDir . '/config.yaml', "theme:\n  mode: dark\n");
    file_put_contents($this->tempDir . '/.mdrc', "theme:\n  mode: light\n");

    $loader = new ConfigLoader($this->tempDir, $globalDir);
    $config = $loader->load();

    expect($config->themeMode)->toBe('light');
});

test('list values are replaced not appended', function () {
    file_put_contents($this->tempDir . '/.mdrc', "navigation:\n  ignore:\n    - build/\n");

    $loader = new ConfigLoader($this->tempDir);
    $config = $loader->load();

    expect($config->ignorePatterns)->toBe(['build/']);
});

test('env var theme override', function () {
    putenv('MD_SERVER_THEME=dark');
    try {
        $loader = new ConfigLoader($this->tempDir);
        $config = $loader->load();
        expect($config->themeMode)->toBe('dark');
    } finally {
        putenv('MD_SERVER_THEME');
    }
});

test('env var no-tree override', function () {
    putenv('MD_SERVER_NO_TREE=1');
    try {
        $loader = new ConfigLoader($this->tempDir);
        $config = $loader->load();
        expect($config->showTree)->toBeFalse();
    } finally {
        putenv('MD_SERVER_NO_TREE');
    }
});

test('malformed .mdrc is ignored', function () {
    file_put_contents($this->tempDir . '/.mdrc', ": invalid: yaml: {{{}");

    $loader = new ConfigLoader($this->tempDir);
    $config = $loader->load();

    expect($config->port)->toBe(8080); // falls back to defaults
});
