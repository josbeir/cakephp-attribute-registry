<?php
declare(strict_types=1);

namespace AttributeRegistry\Service;

use AttributeRegistry\Utility\PathNormalizer;
use Generator;
use RecursiveCallbackFilterIterator;
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
     * @param array<string> $excludePatterns Glob patterns to exclude from results
     */
    public function __construct(
        string $basePath,
        private readonly ?PluginLocator $pluginLocator = null,
        private readonly array $excludePatterns = [],
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
            // Ensure pattern uses forward slashes for consistency
            $pattern = PathNormalizer::toUnixStyle($pattern);

            foreach ($this->expandPattern($basePath, $pattern) as $path) {
                yield $path;
            }
        }
    }

    /**
     * Expand a pattern to file paths using the filtering iterator.
     *
     * Handles both simple patterns and recursive patterns
     * using a unified iterator-based approach.
     *
     * @param string $basePath Base directory path
     * @param string $pattern Glob pattern (may contain wildcards)
     * @return \Generator<string> Generator yielding file paths
     */
    private function expandPattern(string $basePath, string $pattern): Generator
    {
        // Parse pattern to extract directory path and file suffix
        [$scanPath, $suffix] = $this->parsePattern($basePath, $pattern);

        if (!is_dir($scanPath)) {
            return;
        }

        // Create base directory iterator with exclusion support
        $dirIterator = new RecursiveDirectoryIterator(
            $scanPath,
            RecursiveDirectoryIterator::SKIP_DOTS,
        );

        // Wrap with callback filter for exclusions
        $filterIterator = new RecursiveCallbackFilterIterator(
            $dirIterator,
            function ($current, $key, $iterator): bool {
                $path = $current->getPathname();
                $normalizedPath = PathNormalizer::toUnixStyle($path);
                $basename = basename($normalizedPath);

                // Calculate relative path for pattern matching
                $relativePath = $normalizedPath;
                foreach ($this->basePaths as $basePath) {
                    $normalizedBase = PathNormalizer::toUnixStyle($basePath);
                    if (str_starts_with($normalizedPath, $normalizedBase)) {
                        $relativePath = ltrim(substr($normalizedPath, strlen($normalizedBase)), '/');
                        break;
                    }
                }

                // Check exclusion patterns
                foreach ($this->excludePatterns as $pattern) {
                    $normalizedPattern = PathNormalizer::toUnixStyle($pattern);

                    // Match against basename first
                    if (fnmatch($normalizedPattern, $basename)) {
                        return false;
                    }

                    // For recursive directory patterns (e.g., vendor/**, tmp/**)
                    if (str_ends_with($normalizedPattern, '/**')) {
                        $dirPattern = rtrim($normalizedPattern, '/*');
                        if (
                            str_starts_with($relativePath, $dirPattern . '/') ||
                            $relativePath === $dirPattern
                        ) {
                            return false;
                        }
                    }

                    // For patterns starting with ** (e.g., **/Model/**)
                    if (str_starts_with($normalizedPattern, '**/')) {
                        $suffix = substr($normalizedPattern, 3);
                        $suffix = rtrim($suffix, '/*');
                        if (preg_match('#(^|/)' . preg_quote($suffix, '#') . '(\/|$)#', $relativePath)) {
                            return false;
                        }
                    }

                    // Full path glob match
                    if (fnmatch($normalizedPattern, $relativePath)) {
                        return false;
                    }
                }

                return true;
            },
        );

        // Use iterator for file traversal
        $iterator = new RecursiveIteratorIterator(
            $filterIterator,
            RecursiveIteratorIterator::LEAVES_ONLY,
        );

        foreach ($iterator as $file) {
            $filePath = $file->getPathname();
            $normalizedPath = PathNormalizer::normalize($filePath);

            // Apply suffix filter if specified
            if (empty($suffix) || fnmatch($suffix, basename($normalizedPath))) {
                yield $normalizedPath;
            }
        }
    }

    /**
     * Parse a glob pattern into scan path and file suffix.
     *
     * Examples:
     * - 'src/*.php' -> ['src', '*.php']
     * - 'src/**' -> ['src', '']
     * - 'src/**\/*.php' -> ['src', '*.php']
     * - '*.php' -> [basePath, '*.php']
     *
     * @param string $basePath Base directory
     * @param string $pattern Glob pattern
     * @return array{0: string, 1: string} [scanPath, suffix]
     */
    private function parsePattern(string $basePath, string $pattern): array
    {
        // Handle recursive patterns with **
        if (str_contains($pattern, '**')) {
            $parts = explode('**', $pattern, 2);
            $dirPart = rtrim($parts[0], '/\\');
            $suffix = isset($parts[1]) ? ltrim($parts[1], '/\\') : '';

            // If no directory part, use base path
            $scanPath = empty($dirPart)
                ? rtrim($basePath, DIRECTORY_SEPARATOR)
                : rtrim($basePath, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . $dirPart;

            return [$scanPath, $suffix];
        }

        // Handle simple patterns (src/*.php)
        $lastSlash = strrpos($pattern, '/');
        if ($lastSlash !== false) {
            $dirPart = substr($pattern, 0, $lastSlash);
            $suffix = substr($pattern, $lastSlash + 1);

            $scanPath = rtrim($basePath, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . $dirPart;

            return [$scanPath, $suffix];
        }

        // Pattern is just a file pattern (*.php) - scan base path
        return [rtrim($basePath, DIRECTORY_SEPARATOR), $pattern];
    }
}
