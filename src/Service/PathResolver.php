<?php
declare(strict_types=1);

namespace AttributeRegistry\Service;

use Generator;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;

class PathResolver
{
    /**
     * @var array<string>
     */
    private array $basePaths;

    /**
     * Constructor for PathResolver.
     *
     * @param string $basePaths Base paths separated by PATH_SEPARATOR
     */
    public function __construct(
        string $basePaths,
    ) {
        $this->basePaths = array_filter(explode(PATH_SEPARATOR, $basePaths));
    }

    /**
     * Resolve all paths from glob patterns.
     *
     * @param array<string> $globPatterns Array of glob patterns
     * @return \Generator<string> Generator yielding file paths
     */
    public function resolveAllPaths(array $globPatterns): Generator
    {
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
            $fullPattern = rtrim($basePath, DIRECTORY_SEPARATOR) .
                DIRECTORY_SEPARATOR . ltrim($pattern, DIRECTORY_SEPARATOR);
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
            $files = glob($pattern, GLOB_BRACE) ?: [];
            yield from $files;
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
        $basePath = rtrim($parts[0], DIRECTORY_SEPARATOR);
        $suffix = isset($parts[1]) ? ltrim($parts[1], DIRECTORY_SEPARATOR) : '';

        if (!is_dir($basePath)) {
            return;
        }

        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($basePath, RecursiveDirectoryIterator::SKIP_DOTS),
            RecursiveIteratorIterator::SELF_FIRST,
        );

        foreach ($iterator as $file) {
            if ($file->isFile()) {
                $filePath = $file->getPathname();
                if (empty($suffix) || fnmatch('*' . $suffix, basename($filePath))) {
                    yield $filePath;
                }
            }
        }
    }
}
