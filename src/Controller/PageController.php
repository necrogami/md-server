<?php

namespace MdServer\Controller;

use MdServer\Model\Page;
use MdServer\Service\ConfigLoader;
use MdServer\Service\FileTreeBuilder;
use MdServer\Service\MarkdownRenderer;
use MdServer\Service\ThemeResolver;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Mime\MimeTypes;
use Symfony\Component\Routing\Attribute\Route;

class PageController extends AbstractController
{
    public function __construct(
        private readonly MarkdownRenderer $markdownRenderer,
        private readonly FileTreeBuilder $fileTreeBuilder,
        private readonly ThemeResolver $themeResolver,
        private readonly ConfigLoader $configLoader,
    ) {}

    #[Route('/{path}', name: 'page', requirements: ['path' => '.*'], priority: -100)]
    public function __invoke(string $path = ''): Response
    {
        $config = $this->configLoader->load();
        $root = realpath($config->root);

        if ($root === false) {
            throw $this->createNotFoundException('Serving root not found.');
        }

        if ($path === 'favicon.ico') {
            return new Response('', 204);
        }

        $resolved = $this->resolvePath($root, $path);

        if ($resolved === null) {
            return $this->render404($path, $root, $config);
        }

        if (!str_ends_with($resolved, '.md')) {
            return $this->serveStatic($resolved);
        }

        return $this->renderPage($resolved, $path, $root, $config);
    }

    private function resolvePath(string $root, string $path): ?string
    {
        if ($path === '') {
            foreach (['index.md', 'README.md'] as $index) {
                $candidate = $root . '/' . $index;
                if (is_file($candidate)) {
                    return $candidate;
                }
            }
            return null;
        }

        // Try as .md file
        $candidate = $root . '/' . $path . '.md';
        $real = realpath($candidate);
        if ($real !== false && str_starts_with($real, $root) && is_file($real)) {
            return $real;
        }

        // Try as exact path (static files, images)
        $candidate = $root . '/' . $path;
        $real = realpath($candidate);
        if ($real !== false && str_starts_with($real, $root) && is_file($real)) {
            return $real;
        }

        // Try as directory with index
        if ($real !== false && str_starts_with($real, $root) && is_dir($real)) {
            foreach (['index.md', 'README.md'] as $index) {
                $indexPath = $real . '/' . $index;
                if (is_file($indexPath)) {
                    return $indexPath;
                }
            }
        }

        return null;
    }

    private function renderPage(string $filePath, string $urlPath, string $root, $config): Response
    {
        $markdown = file_get_contents($filePath);
        $result = $this->markdownRenderer->render($markdown);

        $page = Page::fromFile(
            str_replace($root . '/', '', $filePath),
            $markdown,
            $result->html,
            $result->frontmatter,
        );

        $theme = $this->themeResolver->resolve($config->themeMode);
        $tree = $config->showTree
            ? $this->fileTreeBuilder->build($root, $config->ignorePatterns)
            : [];

        $response = $this->render('page.html.twig', [
            'page' => $page,
            'tree' => $tree,
            'current_path' => rtrim($urlPath, '/'),
            'breadcrumbs' => $page->breadcrumbs,
            'breadcrumb_paths' => $this->buildBreadcrumbPaths($page->breadcrumbs),
            'theme_mode' => $theme->activeMode,
            'active_theme' => $this->getActiveThemeName($theme),
            'available_themes' => $theme->availableThemes,
            'has_custom_themes' => $theme->hasCustomThemes,
            'override_css' => $theme->overrideCssPath !== null,
            'show_tree' => $config->showTree,
        ]);

        $response->headers->set('Cache-Control', 'no-store');
        return $response;
    }

    private function render404(string $path, string $root, $config): Response
    {
        if ($path === '') {
            return $this->renderGeneratedIndex($root, $config);
        }

        $theme = $this->themeResolver->resolve($config->themeMode);
        $tree = $config->showTree
            ? $this->fileTreeBuilder->build($root, $config->ignorePatterns)
            : [];

        $response = $this->render('404.html.twig', [
            'path' => $path,
            'tree' => $tree,
            'current_path' => '',
            'theme_mode' => $theme->activeMode,
            'active_theme' => $this->getActiveThemeName($theme),
            'available_themes' => $theme->availableThemes,
            'has_custom_themes' => $theme->hasCustomThemes,
            'override_css' => $theme->overrideCssPath !== null,
            'show_tree' => $config->showTree,
        ]);

        $response->setStatusCode(404);
        $response->headers->set('Cache-Control', 'no-store');
        return $response;
    }

    private function renderGeneratedIndex(string $root, $config): Response
    {
        $theme = $this->themeResolver->resolve($config->themeMode);
        $tree = $this->fileTreeBuilder->build($root, $config->ignorePatterns);

        $response = $this->render('index.html.twig', [
            'entries' => $tree,
            'tree' => $tree,
            'current_path' => '',
            'theme_mode' => $theme->activeMode,
            'active_theme' => $this->getActiveThemeName($theme),
            'available_themes' => $theme->availableThemes,
            'has_custom_themes' => $theme->hasCustomThemes,
            'override_css' => $theme->overrideCssPath !== null,
            'show_tree' => $config->showTree,
        ]);

        $response->headers->set('Cache-Control', 'no-store');
        return $response;
    }

    private function serveStatic(string $filePath): Response
    {
        $response = new BinaryFileResponse($filePath);
        $mimeTypes = new MimeTypes();
        $mimeType = $mimeTypes->guessMimeType($filePath) ?? 'application/octet-stream';
        $response->headers->set('Content-Type', $mimeType);
        $response->headers->set('Cache-Control', 'no-store');
        return $response;
    }

    private function getActiveThemeName($theme): string
    {
        if ($theme->activeMode === 'auto') {
            return 'light';
        }
        return $theme->activeMode;
    }

    /** @return string[] */
    private function buildBreadcrumbPaths(array $breadcrumbs): array
    {
        $paths = [];
        $current = '';
        foreach ($breadcrumbs as $crumb) {
            $current = $current === '' ? $crumb : $current . '/' . $crumb;
            $paths[] = $current;
        }
        return $paths;
    }
}
