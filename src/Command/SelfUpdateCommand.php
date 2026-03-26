<?php

namespace MdServer\Command;

use MdServer\Version;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'self-update',
    description: 'Update md-server to the latest version',
)]
class SelfUpdateCommand extends Command
{
    private const string API_URL = 'https://api.github.com/repos/%s/releases/latest';

    protected function configure(): void
    {
        $this
            ->addOption('check', null, InputOption::VALUE_NONE, 'Only check for updates, do not install')
        ;
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        $runtime = PHP_SAPI === 'micro' ? 'static binary' : (\Phar::running() !== '' ? 'phar' : 'source');
        $os = PHP_OS_FAMILY;
        $arch = php_uname('m');

        $io->text(sprintf('Current version: <info>%s</info>', Version::CURRENT));
        $io->text(sprintf('Runtime: <info>%s</info> (%s %s)', $runtime, $os, $arch));

        $release = $this->fetchLatestRelease();
        if ($release === null) {
            $io->error('Failed to fetch latest release from GitHub.');
            return Command::FAILURE;
        }

        $latestVersion = ltrim($release['tag_name'], 'v');
        $currentVersion = ltrim(Version::CURRENT, 'v');

        if ($currentVersion === 'dev') {
            $io->warning('Running a development build. Use --check to see the latest release.');
            if ($input->getOption('check')) {
                $io->text(sprintf('Latest release: <info>%s</info>', $release['tag_name']));
                return Command::SUCCESS;
            }
        }

        if ($currentVersion !== 'dev' && version_compare($currentVersion, $latestVersion, '>=')) {
            $io->success(sprintf('Already up to date (%s).', Version::CURRENT));
            return Command::SUCCESS;
        }

        $io->text(sprintf('Latest version: <info>%s</info>', $release['tag_name']));

        if ($input->getOption('check')) {
            $io->note('Run without --check to install the update.');
            return Command::SUCCESS;
        }

        $assetName = $this->detectAssetName();
        if ($assetName === null) {
            $io->error('Could not determine the correct binary for this platform.');
            return Command::FAILURE;
        }

        $downloadUrl = $this->findAssetUrl($release, $assetName);
        if ($downloadUrl === null) {
            $io->error(sprintf('Asset "%s" not found in release %s.', $assetName, $release['tag_name']));
            return Command::FAILURE;
        }

        $io->text(sprintf('Downloading <info>%s</info>...', $assetName));

        $binaryPath = $this->getCurrentBinaryPath();
        if ($binaryPath === null) {
            $io->error('Could not determine the current binary path.');
            return Command::FAILURE;
        }

        if (!is_writable($binaryPath)) {
            $io->error(sprintf('Cannot write to %s — try running with sudo.', $binaryPath));
            return Command::FAILURE;
        }

        $tempPath = $binaryPath . '.tmp';
        $downloaded = $this->download($downloadUrl, $tempPath);
        if (!$downloaded) {
            @unlink($tempPath);
            $io->error('Download failed.');
            return Command::FAILURE;
        }

        chmod($tempPath, 0755);
        rename($tempPath, $binaryPath);

        $io->success(sprintf('Updated to %s.', $release['tag_name']));
        return Command::SUCCESS;
    }

    private function fetchLatestRelease(): ?array
    {
        $url = sprintf(self::API_URL, Version::GITHUB_REPO);
        $context = stream_context_create([
            'http' => [
                'header' => "User-Agent: md-server/" . Version::CURRENT . "\r\n",
                'timeout' => 10,
            ],
        ]);

        $json = @file_get_contents($url, false, $context);
        if ($json === false) {
            return null;
        }

        $data = json_decode($json, true);
        return is_array($data) && isset($data['tag_name']) ? $data : null;
    }

    private function detectAssetName(): ?string
    {
        // micro SAPI = static binary (micro.sfx + PHAR combined)
        // cli SAPI + Phar::running() = standalone PHAR via `php md-server.phar`
        if (PHP_SAPI !== 'micro' && \Phar::running() !== '') {
            return 'md-server.phar';
        }

        $os = match (PHP_OS_FAMILY) {
            'Darwin' => 'darwin',
            'Windows' => 'windows',
            default => 'linux',
        };

        $arch = match (php_uname('m')) {
            'x86_64', 'amd64', 'AMD64' => 'x86_64',
            'aarch64', 'arm64' => 'aarch64',
            default => null,
        };

        if ($arch === null) {
            return null;
        }

        $name = "md-server-{$os}-{$arch}";
        if ($os === 'windows') {
            $name .= '.exe';
        }

        return $name;
    }

    private function findAssetUrl(array $release, string $assetName): ?string
    {
        foreach ($release['assets'] ?? [] as $asset) {
            if ($asset['name'] === $assetName) {
                return $asset['browser_download_url'];
            }
        }
        return null;
    }

    private function getCurrentBinaryPath(): ?string
    {
        if (PHP_SAPI === 'micro') {
            // Static binary — try multiple methods to find the executable path
            // PHP_BINARY may be empty in micro SAPI
            if (PHP_BINARY !== '') {
                return PHP_BINARY;
            }
            // Linux: /proc/self/exe is a symlink to the running binary
            if (is_link('/proc/self/exe')) {
                return readlink('/proc/self/exe');
            }
            // argv[0] as last resort
            if (isset($_SERVER['argv'][0])) {
                $path = realpath($_SERVER['argv'][0]);
                if ($path !== false) {
                    return $path;
                }
            }
            return null;
        }

        // PHAR — Phar::running(false) returns the filesystem path
        $pharPath = \Phar::running(false);
        if ($pharPath !== '') {
            return $pharPath;
        }

        return null;
    }

    private function download(string $url, string $destination): bool
    {
        $context = stream_context_create([
            'http' => [
                'header' => "User-Agent: md-server/" . Version::CURRENT . "\r\n",
                'follow_location' => true,
                'timeout' => 120,
            ],
        ]);

        $source = @fopen($url, 'r', false, $context);
        if ($source === false) {
            return false;
        }

        $dest = fopen($destination, 'w');
        if ($dest === false) {
            fclose($source);
            return false;
        }

        stream_copy_to_stream($source, $dest);
        fclose($source);
        fclose($dest);

        return filesize($destination) > 0;
    }
}
