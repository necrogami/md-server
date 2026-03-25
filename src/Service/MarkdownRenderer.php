<?php

namespace MdServer\Service;

use League\CommonMark\Environment\Environment;
use League\CommonMark\Extension\CommonMark\CommonMarkCoreExtension;
use League\CommonMark\Extension\FrontMatter\FrontMatterExtension;
use League\CommonMark\Extension\FrontMatter\Output\RenderedContentWithFrontMatter;
use League\CommonMark\Extension\GithubFlavoredMarkdownExtension;
use League\CommonMark\MarkdownConverter;

class MarkdownRenderer
{
    private MarkdownConverter $converter;

    public function __construct(private readonly array $extensions = [])
    {
        $environment = new Environment([]);
        $environment->addExtension(new CommonMarkCoreExtension());

        if ($this->extensions['gfm'] ?? true) {
            $environment->addExtension(new GithubFlavoredMarkdownExtension());
        }
        if ($this->extensions['frontmatter'] ?? true) {
            $environment->addExtension(new FrontMatterExtension());
        }

        $this->converter = new MarkdownConverter($environment);
    }

    public static function fromConfig(ConfigLoader $configLoader): self
    {
        $config = $configLoader->load();
        return new self($config->extensions);
    }

    public function render(string $markdown): RenderResult
    {
        $markdown = $this->preprocessMermaid($markdown);
        $markdown = $this->preprocessBlockMath($markdown);

        $result = $this->converter->convert($markdown);

        $frontmatter = [];
        if ($result instanceof RenderedContentWithFrontMatter) {
            $frontmatter = $result->getFrontMatter() ?? [];
        }

        $html = $result->getContent();
        $html = $this->rewriteLinks($html);

        return new RenderResult($html, $frontmatter);
    }

    private function preprocessMermaid(string $markdown): string
    {
        return preg_replace_callback(
            '/^```mermaid\s*\n(.*?)^```\s*$/ms',
            fn (array $m) => "\n<pre class=\"mermaid\">" . htmlspecialchars($m[1]) . "</pre>\n",
            $markdown,
        );
    }

    private function preprocessBlockMath(string $markdown): string
    {
        if (!($this->extensions['math'] ?? true)) return $markdown;

        return preg_replace_callback(
            '/^\$\$\s*\n(.*?)^\$\$\s*$/ms',
            fn (array $m) => "\n<div class=\"katex-block\">" . htmlspecialchars(trim($m[1])) . "</div>\n",
            $markdown,
        );
    }

    private function rewriteLinks(string $html): string
    {
        // Rewrite internal .md links to clean URLs
        $html = preg_replace_callback(
            '/href="(\.\/|\.\.\/)?([^"]*?)\.md(")/i',
            function (array $m) {
                $path = ltrim($m[2], './');
                return 'href="/' . $path . $m[3];
            },
            $html,
        );

        // Add target="_blank" to external links
        $html = preg_replace_callback(
            '/<a\s+href="(https?:\/\/[^"]*)"/',
            fn (array $m) => '<a href="' . $m[1] . '" target="_blank" rel="noopener noreferrer"',
            $html,
        );

        return $html;
    }
}
