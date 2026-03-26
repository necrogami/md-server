<?php

namespace MdServer\Command;

use MdServer\Kernel;
use Nyholm\Psr7\Factory\Psr17Factory;
use React\EventLoop\Loop;
use React\Http\HttpServer;
use React\Http\Message\Response as ReactResponse;
use React\Socket\SocketServer;
use Symfony\Bridge\PsrHttpMessage\Factory\HttpFoundationFactory;
use Symfony\Bridge\PsrHttpMessage\Factory\PsrHttpFactory;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

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

        // Set env vars for the kernel to pick up
        $_ENV['MD_SERVER_ROOT'] = $root;
        $_SERVER['MD_SERVER_ROOT'] = $root;

        if ($input->getOption('theme')) {
            putenv('MD_SERVER_THEME=' . $input->getOption('theme'));
        }
        if ($input->getOption('no-tree')) {
            putenv('MD_SERVER_NO_TREE=1');
        }

        // Boot a fresh Symfony kernel for handling HTTP requests
        $kernel = new Kernel('prod', false);
        $kernel->boot();

        $psr17Factory = new Psr17Factory();
        $httpFoundationFactory = new HttpFoundationFactory();
        $psrHttpFactory = new PsrHttpFactory($psr17Factory, $psr17Factory, $psr17Factory, $psr17Factory);

        $server = new HttpServer(function (\Psr\Http\Message\ServerRequestInterface $request) use ($kernel, $httpFoundationFactory, $psrHttpFactory, $output) {
            try {
                $symfonyRequest = $httpFoundationFactory->createRequest($request);
                $symfonyResponse = $kernel->handle($symfonyRequest);

                $output->writeln(sprintf(
                    '<info>[%s]</info> %s %s <comment>%d</comment>',
                    date('H:i:s'),
                    $symfonyRequest->getMethod(),
                    $symfonyRequest->getPathInfo(),
                    $symfonyResponse->getStatusCode(),
                ));

                $psrResponse = $psrHttpFactory->createResponse($symfonyResponse);
                $kernel->terminate($symfonyRequest, $symfonyResponse);

                return $psrResponse;
            } catch (\Throwable $e) {
                $output->writeln(sprintf('<error>[%s] %s: %s</error>', date('H:i:s'), get_class($e), $e->getMessage()));
                return new ReactResponse(500, ['Content-Type' => 'text/plain'], 'Internal Server Error');
            }
        });

        $listen = "{$host}:{$port}";
        $socket = new SocketServer($listen);

        $io->success(sprintf('md-server started on http://%s', $listen));
        $io->text(sprintf('Serving: %s', $root));
        $io->text('Press Ctrl+C to stop.');

        $server->listen($socket);

        return Command::SUCCESS;
    }
}
