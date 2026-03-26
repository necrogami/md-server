<?php

use MdServer\Command\SelfUpdateCommand;
use MdServer\Version;

test('command has correct name', function () {
    $command = new SelfUpdateCommand();
    expect($command->getName())->toBe('self-update');
});

test('has check option', function () {
    $command = new SelfUpdateCommand();
    expect($command->getDefinition()->hasOption('check'))->toBeTrue();
});

test('version constant exists', function () {
    expect(Version::CURRENT)->toBeString();
    expect(Version::GITHUB_REPO)->toBe('necrogami/md-server');
});
