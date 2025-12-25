<?php
declare(strict_types=1);

namespace AttributeRegistry\Service;

use AttributeRegistry\Utility\PathNormalizer;
use Generator;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;

class PathResolver
{
    /**
     * @var array<string>
     */
    private array $basePaths;

    private bool $pathsResolved = false;

    /**
     * Constructor for PathResolver.
     *
     * @param string $basePath Primary base path (typically ROOT)
     * @param \AttributeRegistry\Service\PluginLocator|null $pluginLocator Optional plugin locator for lazy plugin path resolution
     */
    public function __construct(
        string $basePath,
        private readonly ?PluginLocator $pluginLocator = null,
    ) {
        $this->basePaths = array_filter(explode(PATH_SEPARATOR, $basePath));
    }

    /**
     * Ensure all paths (including plugin paths) are resolved.
     * Only resolves once on first call for performance.
     */
    private function ensureAllPathsResolved(): void
    {
        if ($this->pathsResolved) {
            return;
        }

        if ($this->pluginLocator instanceof PluginLocator) {
            $pluginPaths = $this->pluginLocator->getEnabledPluginPaths();
            foreach ($pluginPaths as $path) {
                $this->basePaths[] = $path;
            }
        }

        $this->pathsResolved = true;
    }

    /**
     * Resolve all paths from glob patterns.
     * Lazily resolves plugin paths on first invocation.
     *
     * @param array<string> $globPatterns Array of glob patterns
     * @return \Generator<string> Generator yielding file paths
     */
    public function resolveAllPaths(array $globPatterns): Generator
    {
        $this->ensureAllPathsResolved();

        foreach ($this->basePaths as $basePath) {
            foreach ($this->resolvePatternsForPath($basePath, $globPatterns) as $path) {
                yield $path;
            }
        }
    }

    /**
     * Resolve patterns for a specific base path.
     *
     * @param string $basePath Base path to resolve against
     * @param array<string> $globPatterns Array of glob patterns
     * @return \Generator<string> Generator yielding file paths
     */
    private function resolvePatternsForPath(string $basePath, array $globPatterns): Generator
    {
        foreach ($globPatterns as $pattern) {
            // Ensure pattern uses forward slashes for glob compatibility on all platforms
            $pattern = PathNormalizer::toUnixStyle($pattern);

            $fullPattern = rtrim($basePath, DIRECTORY_SEPARATOR) .
                DIRECTORY_SEPARATOR . ltrim($pattern, '/');
            foreach ($this->expandGlobPattern($fullPattern) as $path) {
                yield $path;
            }
        }
    }

    /**
     * Expand a glob pattern to file paths.
     *
     * @param string $pattern Glob pattern
     * @return \Generator<string> Generator yielding file paths
     */
    private function expandGlobPattern(string $pattern): Generator
    {
        if (strpos($pattern, '**') !== false) {
            yield from $this->expandRecursivePattern($pattern);
        } else {
            // Convert to forward slashes for glob on all platforms
            $pattern = PathNormalizer::toUnixStyle($pattern);
            $files = glob($pattern, GLOB_BRACE | GLOB_NOSORT) ?: [];
            foreach ($files as $file) {
                // Normalize returned paths to platform separator
                yield PathNormalizer::normalize($file);
            }
        }
    }

    /**
     * Expand a recursive pattern (containing **) to file paths.
     *
     * @param string $pattern Recursive glob pattern
     * @return \Generator<string> Generator yielding file paths
     */
    private function expandRecursivePattern(string $pattern): Generator
    {
        $parts = explode('**', $pattern, 2);
        $basePath = rtrim($parts[0], '/\\');
        $suffix = isset($parts[1]) ? ltrim($parts[1], '/\\') : '';

        if (!is_dir($basePath)) {
            return;
        }

        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($basePath, RecursiveDirectoryIterator::SKIP_DOTS),
            RecursiveIteratorIterator::LEAVES_ONLY,
        );

        foreach ($iterator as $file) {
            $filePath = $file->getPathname();
            // Normalize path separators for consistent comparison
            $normalizedPath = PathNormalizer::normalize($filePath);
            if (empty($suffix) || fnmatch('*' . $suffix, basename($normalizedPath))) {
                yield $normalizedPath;
            }
        }
    }
}
