<?php
declare(strict_types=1);

namespace AttributeRegistry\Test\TestCase;

use AttributeRegistry\AttributeRegistry;
use AttributeRegistry\Service\AttributeCache;
use AttributeRegistry\Service\AttributeParser;
use AttributeRegistry\Service\AttributeScanner;
use AttributeRegistry\Service\PathResolver;

/**
 * Trait providing factory methods for common test objects.
 *
 * Reduces duplication across test cases by providing consistent
 * creation of AttributeParser, AttributeScanner, and AttributeRegistry instances.
 */
trait AttributeRegistryTestTrait
{
    /**
     * Create a new AttributeParser instance.
     */
    protected function createParser(): AttributeParser
    {
        return new AttributeParser();
    }

    /**
     * Create a new PathResolver instance.
     *
     * @param string|null $basePath Base path for resolution (defaults to test data path)
     */
    protected function createPathResolver(?string $basePath = null): PathResolver
    {
        return new PathResolver($basePath ?? $this->getTestDataPath());
    }

    /**
     * Create a new AttributeCache instance.
     *
     * @param string $cacheKey Cache configuration key
     * @param bool $enabled Whether caching is enabled
     */
    protected function createCache(string $cacheKey, bool $enabled = false): AttributeCache
    {
        return new AttributeCache($cacheKey, $enabled);
    }

    /**
     * Create a new AttributeScanner instance with default test configuration.
     *
     * @param AttributeParser|null $parser Parser instance (creates new if null)
     * @param PathResolver|null $pathResolver Path resolver instance (creates new if null)
     * @param array<string, mixed> $config Scanner configuration options
     */
    protected function createScanner(
        ?AttributeParser $parser = null,
        ?PathResolver $pathResolver = null,
        array $config = [],
    ): AttributeScanner {
        return new AttributeScanner(
            $parser ?? $this->createParser(),
            $pathResolver ?? $this->createPathResolver(),
            $config + $this->getDefaultScannerConfig(),
        );
    }

    /**
     * Create a new AttributeRegistry instance with default test configuration.
     *
     * @param string $cacheKey Cache configuration key
     * @param bool $cacheEnabled Whether caching is enabled
     * @param array<string, mixed> $scannerConfig Scanner configuration options
     */
    protected function createRegistry(
        string $cacheKey,
        bool $cacheEnabled = false,
        array $scannerConfig = [],
    ): AttributeRegistry {
        $scanner = $this->createScanner(config: $scannerConfig);
        $cache = $this->createCache($cacheKey, $cacheEnabled);

        return new AttributeRegistry($scanner, $cache);
    }

    /**
     * Get the path to the test data directory.
     */
    protected function getTestDataPath(): string
    {
        return dirname(__DIR__) . '/data';
    }

    /**
     * Get default scanner configuration for tests.
     *
     * @return array<string, mixed>
     */
    protected function getDefaultScannerConfig(): array
    {
        return [
            'paths' => ['*.php'],
            'exclude_paths' => [],
        ];
    }

    /**
     * Load test attribute classes.
     */
    protected function loadTestAttributes(): void
    {
        require_once $this->getTestDataPath() . '/TestAttributes.php';
    }
}
