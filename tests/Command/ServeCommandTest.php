<?php

use MdServer\Command\ServeCommand;
use Symfony\Component\Console\Tester\CommandTester;

test('command has correct name', function () {
    $command = new ServeCommand();
    expect($command->getName())->toBe('serve');
});

test('default options', function () {
    $command = new ServeCommand();
    $definition = $command->getDefinition();

    expect($definition->hasOption('port'))->toBeTrue();
    expect($definition->hasOption('host'))->toBeTrue();
    expect($definition->hasOption('root'))->toBeTrue();
    expect($definition->getOption('port')->getDefault())->toBe('8080');
    expect($definition->getOption('host')->getDefault())->toBe('127.0.0.1');
});

test('rejects invalid root', function () {
    $command = new ServeCommand();
    $tester = new CommandTester($command);

    $tester->execute(['--root' => '/nonexistent/path/that/does/not/exist']);

    expect($tester->getStatusCode())->toBe(1);
    expect($tester->getDisplay())->toContain('not found');
});
