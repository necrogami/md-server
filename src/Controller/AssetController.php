<?php

namespace MdServer\Controller;

use MdServer\Service\ConfigLoader;
use MdServer\Service\ThemeResolver;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/_md')]
class AssetController extends AbstractController
{
    public function __construct(
        private readonly ThemeResolver $themeResolver,
        private readonly ConfigLoader $configLoader,
    ) {}

    #[Route('/css/{name}.css', name: 'asset_css')]
    public function css(string $name): Response
    {
        $path = $this->getAssetsDir() . '/css/' . $name . '.css';
        return $this->serveFile($path, 'text/css');
    }

    #[Route('/js/{name}.js', name: 'asset_js')]
    public function js(string $name): Response
    {
        $path = $this->getAssetsDir() . '/js/' . $name . '.js';
        return $this->serveFile($path, 'application/javascript');
    }

    #[Route('/vendor/{library}/{file}', name: 'asset_vendor', requirements: ['file' => '.+'])]
    public function vendor(string $library, string $file): Response
    {
        $path = $this->getAssetsDir() . '/vendor/' . $library . '/' . $file;

        $contentType = match (true) {
            str_ends_with($file, '.css') => 'text/css',
            str_ends_with($file, '.js') => 'application/javascript',
            str_ends_with($file, '.woff2') => 'font/woff2',
            str_ends_with($file, '.woff') => 'font/woff',
            str_ends_with($file, '.ttf') => 'font/ttf',
            default => 'application/octet-stream',
        };

        return $this->serveFile($path, $contentType);
    }

    #[Route('/theme/override.css', name: 'asset_theme_override')]
    public function themeOverride(): Response
    {
        $config = $this->configLoader->load();
        $theme = $this->themeResolver->resolve($config->themeMode);

        if ($theme->overrideCssPath === null || !is_file($theme->overrideCssPath)) {
            throw $this->createNotFoundException();
        }

        return $this->serveFile($theme->overrideCssPath, 'text/css');
    }

    #[Route('/theme/{name}.css', name: 'asset_theme_custom')]
    public function themeCustom(string $name): Response
    {
        $config = $this->configLoader->load();
        $theme = $this->themeResolver->resolve($config->themeMode);

        if (!isset($theme->themePaths[$name])) {
            throw $this->createNotFoundException();
        }

        return $this->serveFile($theme->themePaths[$name], 'text/css');
    }

    private function serveFile(string $path, string $contentType): Response
    {
        // realpath() doesn't work with phar:// paths — use is_file() directly
        if (!is_file($path)) {
            throw $this->createNotFoundException();
        }

        // BinaryFileResponse doesn't support phar:// — read content directly
        if (str_starts_with($path, 'phar://')) {
            $content = file_get_contents($path);
            if ($content === false) {
                throw $this->createNotFoundException();
            }
            $response = new Response($content);
            $response->headers->set('Content-Type', $contentType);
            $response->headers->set('Cache-Control', 'no-store');
            return $response;
        }

        $response = new BinaryFileResponse($path);
        $response->headers->set('Content-Type', $contentType);
        $response->headers->set('Cache-Control', 'no-store');
        return $response;
    }

    private function getAssetsDir(): string
    {
        return dirname(__DIR__, 2) . '/assets';
    }
}
