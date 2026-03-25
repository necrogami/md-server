<?php

use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

uses(WebTestCase::class);

beforeEach(function () {
    $this->fixtureDir = sys_get_temp_dir() . '/md-page-test-' . uniqid();
    mkdir($this->fixtureDir, 0755, true);

    $_ENV['MD_SERVER_ROOT'] = $this->fixtureDir;
    $_SERVER['MD_SERVER_ROOT'] = $this->fixtureDir;
});

afterEach(function () {
    rmdir_recursive($this->fixtureDir);
    unset($_ENV['MD_SERVER_ROOT'], $_SERVER['MD_SERVER_ROOT']);
    static::ensureKernelShutdown();
});

test('renders markdown page', function () {
    file_put_contents($this->fixtureDir . '/guide.md', '# My Guide');

    $client = static::createClient();
    $client->request('GET', '/guide');

    expect($client->getResponse()->getStatusCode())->toBe(200);
    expect($client->getResponse()->getContent())->toContain('My Guide');
});

test('returns 404 for missing page', function () {
    $client = static::createClient();
    $client->request('GET', '/nonexistent');

    expect($client->getResponse()->getStatusCode())->toBe(404);
});

test('serves root index.md', function () {
    file_put_contents($this->fixtureDir . '/index.md', '# Welcome');

    $client = static::createClient();
    $client->request('GET', '/');

    expect($client->getResponse()->getStatusCode())->toBe(200);
    expect($client->getResponse()->getContent())->toContain('Welcome');
});

test('serves generated index when no index.md', function () {
    file_put_contents($this->fixtureDir . '/guide.md', '# Guide');

    $client = static::createClient();
    $client->request('GET', '/');

    expect($client->getResponse()->getStatusCode())->toBe(200);
    expect($client->getResponse()->getContent())->toContain('Documentation');
});

test('blocks path traversal', function () {
    $client = static::createClient();
    $client->request('GET', '/../../../etc/passwd');

    expect($client->getResponse()->getStatusCode())->toBe(404);
});

test('serves README.md as root fallback', function () {
    file_put_contents($this->fixtureDir . '/README.md', '# Readme');

    $client = static::createClient();
    $client->request('GET', '/');

    expect($client->getResponse()->getStatusCode())->toBe(200);
    expect($client->getResponse()->getContent())->toContain('Readme');
});

test('serves static files from serving root', function () {
    file_put_contents($this->fixtureDir . '/style.css', 'body { color: red; }');

    $client = static::createClient();
    $client->request('GET', '/style.css');

    expect($client->getResponse()->getStatusCode())->toBe(200);
});

test('serves directory index.md', function () {
    mkdir($this->fixtureDir . '/docs', 0755);
    file_put_contents($this->fixtureDir . '/docs/index.md', '# Docs');

    $client = static::createClient();
    $client->request('GET', '/docs');

    expect($client->getResponse()->getStatusCode())->toBe(200);
    expect($client->getResponse()->getContent())->toContain('Docs');
});
