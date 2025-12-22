<?php
declare(strict_types=1);

namespace AttributeRegistry\Service;

use Cake\Log\Log;
use Generator;
use Throwable;

/**
 * Service for scanning files and discovering PHP attributes.
 */
class AttributeScanner
{
    /**
     * Default configuration.
     *
     * @var array<string, mixed>
     */
    private array $config;

    /**
     * Constructor for AttributeScanner.
     *
     * @param \AttributeRegistry\Service\AttributeParser $parser Attribute parser
     * @param \AttributeRegistry\Service\PathResolver $pathResolver Path resolver
     * @param array<string, mixed> $config Scanner configuration
     */
    public function __construct(
        private AttributeParser $parser,
        private PathResolver $pathResolver,
        array $config = [],
    ) {
        $this->config = array_merge([
            'paths' => ['src/**/*.php'],
            'exclude_paths' => ['vendor/**', 'tmp/**'],
            'max_file_size' => 1024 * 1024,
        ], $config);
    }

    /**
     * Scan all configured paths and return discovered attributes.
     *
     * @return \Generator<\AttributeRegistry\ValueObject\AttributeInfo>
     */
    public function scanAll(): Generator
    {
        foreach ($this->pathResolver->resolveAllPaths($this->config['paths']) as $filePath) {
            if ($this->shouldScanFile($filePath)) {
                foreach ($this->scanFile($filePath) as $attributeInfo) {
                    yield $attributeInfo;
                }
            }
        }
    }

    /**
     * Check if a file should be scanned based on configuration.
     *
     * @param string $filePath File path to check
     * @return bool True if file should be scanned
     */
    private function shouldScanFile(string $filePath): bool
    {
        // Check file extension
        if (pathinfo($filePath, PATHINFO_EXTENSION) !== 'php') {
            return false;
        }

        // Check file size
        $fileSize = filesize($filePath);
        if ($fileSize === false || $fileSize > $this->config['max_file_size']) {
            return false;
        }

        // Check exclude patterns
        $filename = basename($filePath);
        foreach ($this->config['exclude_paths'] as $excludePattern) {
            if (fnmatch($excludePattern, $filename) || fnmatch($excludePattern, $filePath)) {
                return false;
            }
        }

        return true;
    }

    /**
     * Scan a single file for attributes.
     *
     * @param string $filePath File path to scan
     * @return array<\AttributeRegistry\ValueObject\AttributeInfo>
     */
    private function scanFile(string $filePath): array
    {
        try {
            return $this->parser->parseFile($filePath);
        } catch (Throwable $throwable) {
            Log::warning(sprintf('Failed to parse file %s: ', $filePath) . $throwable->getMessage());

            return [];
        }
    }
}
