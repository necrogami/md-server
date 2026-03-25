<?php

use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

uses(WebTestCase::class);

beforeEach(function () {
    $this->fixtureDir = sys_get_temp_dir() . '/md-asset-test-' . uniqid();
    mkdir($this->fixtureDir, 0755, true);

    $_ENV['MD_SERVER_ROOT'] = $this->fixtureDir;
    $_SERVER['MD_SERVER_ROOT'] = $this->fixtureDir;
});

afterEach(function () {
    rmdir_recursive($this->fixtureDir);
    unset($_ENV['MD_SERVER_ROOT'], $_SERVER['MD_SERVER_ROOT']);
    static::ensureKernelShutdown();
});

test('serves built-in CSS files', function () {
    $cssDir = dirname(__DIR__, 2) . '/assets/css';
    if (!is_file($cssDir . '/light.css')) {
        $this->markTestSkipped('CSS assets not yet created');
    }

    $client = static::createClient();
    $client->request('GET', '/_md/css/light.css');

    expect($client->getResponse()->getStatusCode())->toBe(200);
    expect($client->getResponse()->headers->get('Content-Type'))->toContain('text/css');
});

test('returns 404 for missing asset', function () {
    $client = static::createClient();
    $client->request('GET', '/_md/css/nonexistent.css');

    expect($client->getResponse()->getStatusCode())->toBe(404);
});

test('serves JavaScript files', function () {
    $jsDir = dirname(__DIR__, 2) . '/assets/js';
    if (!is_file($jsDir . '/app.js')) {
        $this->markTestSkipped('JS assets not yet created');
    }

    $client = static::createClient();
    $client->request('GET', '/_md/js/app.js');

    expect($client->getResponse()->getStatusCode())->toBe(200);
    expect($client->getResponse()->headers->get('Content-Type'))->toContain('application/javascript');
});
