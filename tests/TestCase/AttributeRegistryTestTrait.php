<?php
declare(strict_types=1);

namespace AttributeRegistry\Test\TestCase;

use AttributeRegistry\AttributeRegistry;
use AttributeRegistry\Service\AttributeParser;
use AttributeRegistry\Service\AttributeScanner;
use AttributeRegistry\Service\CompiledCache;
use AttributeRegistry\Service\PathResolver;
use AttributeRegistry\Service\PluginPathResolver;

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
     * Create a new CompiledCache instance.
     *
     * @param string $cachePath Cache directory path (relative paths will be placed in TMP/cache)
     * @param bool $enabled Whether caching is enabled
     */
    protected function createCache(string $cachePath, bool $enabled = false): CompiledCache
    {
        // Convert relative paths to absolute paths under TMP/cache
        if (!str_starts_with($cachePath, '/') && !str_contains($cachePath, ':\\')) {
            $cachePath = CACHE . $cachePath;
        }

        return new CompiledCache($cachePath, $enabled);
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
     * @param string $cachePath Cache directory path
     * @param bool $cacheEnabled Whether caching is enabled
     * @param array<string, mixed> $scannerConfig Scanner configuration options
     */
    protected function createRegistry(
        string $cachePath,
        bool $cacheEnabled = false,
        array $scannerConfig = [],
    ): AttributeRegistry {
        $scanner = $this->createScanner(config: $scannerConfig);
        $cache = $this->createCache($cachePath, $cacheEnabled);

        return new AttributeRegistry($scanner, $cache);
    }

    /**
     * Create a new AttributeRegistry instance that includes plugin paths.
     *
     * This is useful for integration tests that need to discover attributes
     * from loaded plugins (including local plugins).
     *
     * @param string $cachePath Cache directory path
     * @param bool $cacheEnabled Whether caching is enabled
     * @param array<string, mixed> $scannerConfig Scanner configuration options
     */
    protected function createRegistryWithPlugins(
        string $cachePath,
        bool $cacheEnabled = false,
        array $scannerConfig = [],
    ): AttributeRegistry {
        // Build path string including test data + all loaded plugins
        $pluginPathResolver = new PluginPathResolver();
        $pluginPaths = $pluginPathResolver->getEnabledPluginPaths();
        $allPaths = array_merge([$this->getTestDataPath()], $pluginPaths);
        $pathString = implode(PATH_SEPARATOR, $allPaths);

        $pathResolver = new PathResolver($pathString);
        $parser = $this->createParser();

        // Use src/**/*.php for plugins, *.php for test data
        $defaultConfig = [
            'paths' => ['*.php', 'src/**/*.php'],
            'exclude_paths' => [],
        ];

        $scanner = new AttributeScanner(
            $parser,
            $pathResolver,
            $scannerConfig + $defaultConfig,
        );
        $cache = $this->createCache($cachePath, $cacheEnabled);

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

    /**
     * Load test plugins including local plugin.
     */
    protected function loadTestPlugins(): void
    {
        $this->loadPlugins(['TestLocalPlugin']);
    }
}
