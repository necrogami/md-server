<?php

use MdServer\Model\Config;

test('default values', function () {
    $config = Config::fromArray([]);

    expect($config->host)->toBe('127.0.0.1');
    expect($config->port)->toBe(8080);
    expect($config->themeMode)->toBe('auto');
    expect($config->showTree)->toBeTrue();
    expect($config->extensions['gfm'])->toBeTrue();
});

test('fromArray overrides defaults', function () {
    $config = Config::fromArray([
        'server' => ['port' => 3000],
        'theme' => ['mode' => 'dark'],
    ]);

    expect($config->port)->toBe(3000);
    expect($config->themeMode)->toBe('dark');
    expect($config->host)->toBe('127.0.0.1'); // default preserved
});

test('list values are replaced not merged', function () {
    $config = Config::fromArray([
        'navigation' => ['ignore' => ['build/']],
    ]);

    expect($config->ignorePatterns)->toBe(['build/']);
});

test('immutability', function () {
    $config = Config::fromArray([]);
    expect($config->isReadonly())->toBeTrue();
});
