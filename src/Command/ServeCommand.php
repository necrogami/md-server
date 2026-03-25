<?php

namespace MdServer\Command;

use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\Process\Process;

#[AsCommand(
    name: 'serve',
    description: 'Start the markdown documentation server',
)]
class ServeCommand extends Command
{
    protected function configure(): void
    {
        $this
            ->addOption('port', 'p', InputOption::VALUE_REQUIRED, 'Port to listen on', '8080')
            ->addOption('host', null, InputOption::VALUE_REQUIRED, 'Host to bind to', '127.0.0.1')
            ->addOption('root', 'r', InputOption::VALUE_REQUIRED, 'Document root directory', '.')
            ->addOption('theme', 't', InputOption::VALUE_REQUIRED, 'Theme mode (light, dark, auto)')
            ->addOption('no-tree', null, InputOption::VALUE_NONE, 'Disable sidebar tree navigation')
        ;
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        $root = realpath($input->getOption('root'));
        if ($root === false || !is_dir($root)) {
            $io->error(sprintf('Serving root not found: %s', $input->getOption('root')));
            return Command::FAILURE;
        }

        $host = $input->getOption('host');
        $port = $input->getOption('port');

        $routerPath = $this->findRouterScript();
        if ($routerPath === null) {
            $io->error('Could not locate router.php');
            return Command::FAILURE;
        }

        $env = [
            'MD_SERVER_ROOT' => $root,
            'APP_ENV' => 'prod',
            'APP_DEBUG' => '0',
        ];

        if ($input->getOption('theme')) {
            $env['MD_SERVER_THEME'] = $input->getOption('theme');
        }
        if ($input->getOption('no-tree')) {
            $env['MD_SERVER_NO_TREE'] = '1';
        }

        $process = new Process(
            [PHP_BINARY, '-S', $host . ':' . $port, $routerPath],
            $root,
            $env,
        );

        $process->setTimeout(null);

        $io->success(sprintf('md-server started on http://%s:%s', $host, $port));
        $io->text(sprintf('Serving: %s', $root));
        $io->text('Press Ctrl+C to stop.');

        $process->start(function (string $type, string $buffer) use ($output) {
            $output->write($buffer);
        });

        usleep(500_000);
        if (!$process->isRunning()) {
            $io->error('Server failed to start:');
            $io->text($process->getErrorOutput());
            return Command::FAILURE;
        }

        $shutdownHandler = function () use ($process) {
            if ($process->isRunning()) {
                $process->signal(15);
                $process->wait();
            }
        };

        register_shutdown_function($shutdownHandler);

        if (function_exists('pcntl_signal')) {
            pcntl_signal(SIGINT, function () use ($shutdownHandler) {
                $shutdownHandler();
                exit(0);
            });
            pcntl_signal(SIGTERM, function () use ($shutdownHandler) {
                $shutdownHandler();
                exit(0);
            });
        }

        $process->wait();

        return Command::SUCCESS;
    }

    private function findRouterScript(): ?string
    {
        $pharPath = \Phar::running();
        if ($pharPath !== '') {
            return $pharPath . '/router.php';
        }

        $projectRoot = dirname(__DIR__, 2);
        $routerPath = $projectRoot . '/router.php';
        if (is_file($routerPath)) {
            return $routerPath;
        }

        return null;
    }
}
