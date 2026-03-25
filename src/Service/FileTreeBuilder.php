<?php
namespace MdServer\Service;

use MdServer\Model\TreeNode;

class FileTreeBuilder
{
    /** @return TreeNode[] */
    public function build(string $root, array $ignorePatterns = []): array
    {
        return $this->scanDirectory($root, $root, $ignorePatterns);
    }

    /** @return TreeNode[] */
    private function scanDirectory(string $dir, string $root, array $ignorePatterns): array
    {
        $dirs = [];
        $files = [];
        $items = scandir($dir);
        if ($items === false) return [];

        foreach ($items as $item) {
            if ($item === '.' || $item === '..') continue;

            $fullPath = $dir . '/' . $item;
            $relativePath = ltrim(str_replace($root, '', $fullPath), '/');
            $isDir = is_dir($fullPath);

            if ($this->isIgnored($item, $isDir, $ignorePatterns)) continue;

            // Security: verify symlinks resolve within root
            $realFullPath = realpath($fullPath);
            if ($realFullPath === false || !str_starts_with($realFullPath, realpath($root))) continue;
            if (!is_readable($fullPath)) continue;

            if ($isDir) {
                $children = $this->scanDirectory($fullPath, $root, $ignorePatterns);
                if ($children !== []) {
                    $dirs[] = TreeNode::directory($item, $relativePath, $children);
                }
            } elseif ($this->isMarkdownFile($item)) {
                $title = $this->extractTitle($fullPath);
                $files[] = TreeNode::file($item, $relativePath, $title);
            }
        }

        usort($dirs, fn (TreeNode $a, TreeNode $b) => strcasecmp($a->name, $b->name));
        usort($files, fn (TreeNode $a, TreeNode $b) => strcasecmp($a->name, $b->name));

        return array_merge($dirs, $files);
    }

    private function isMarkdownFile(string $filename): bool
    {
        return (bool) preg_match('/\.md$/i', $filename);
    }

    private function isIgnored(string $name, bool $isDir, array $patterns): bool
    {
        foreach ($patterns as $pattern) {
            if (str_ends_with($pattern, '/') && $isDir) {
                if ($name === rtrim($pattern, '/')) return true;
            }
            if (fnmatch($pattern, $name)) return true;
        }
        return false;
    }

    private function extractTitle(string $filePath): ?string
    {
        $handle = @fopen($filePath, 'r');
        if ($handle === false) return null;

        $lines = [];
        $lineCount = 0;
        while ($lineCount < 10 && ($line = fgets($handle)) !== false) {
            $lines[] = $line;
            $lineCount++;
        }
        fclose($handle);

        $content = implode('', $lines);
        if (!str_starts_with(trim($content), '---')) return null;

        $parts = preg_split('/^---\s*$/m', $content, 3);
        if ($parts === false || count($parts) < 3) return null;

        if (preg_match('/^title:\s*(.+)$/m', $parts[1], $matches)) {
            return trim($matches[1], " \t\n\r\0\x0B\"'");
        }
        return null;
    }
}
