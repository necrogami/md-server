<?php

namespace MdServer\Console;

use MdServer\Version;
use Symfony\Bundle\FrameworkBundle\Console\Application as BaseApplication;
use Symfony\Component\HttpKernel\KernelInterface;

class Application extends BaseApplication
{
    private const array ALLOWED_COMMANDS = [
        'help',
        'list',
        'serve',
        'self-update',
    ];

    public function __construct(KernelInterface $kernel)
    {
        parent::__construct($kernel);
        $this->setName('md-server');
        $this->setVersion(Version::CURRENT);

        // Remove Symfony-internal options not useful to end users
        $definition = $this->getDefinition();
        $options = $definition->getOptions();
        unset($options['profile']);
        $definition->setOptions($options);
    }

    public function all(?string $namespace = null): array
    {
        return array_filter(
            parent::all($namespace),
            fn ($cmd) => in_array($cmd->getName(), self::ALLOWED_COMMANDS, true),
        );
    }

    public function getLongVersion(): string
    {
        return sprintf('md-server <info>%s</info>', Version::CURRENT);
    }
}
