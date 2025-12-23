<?php
declare(strict_types=1);

namespace AttributeRegistry;

use AttributeRegistry\Collection\AttributeCollection;
use AttributeRegistry\Enum\AttributeTargetType;
use AttributeRegistry\Service\AttributeCache;
use AttributeRegistry\Service\AttributeParser;
use AttributeRegistry\Service\AttributeScanner;
use AttributeRegistry\Service\PathResolver;
use AttributeRegistry\Service\PluginPathResolver;
use AttributeRegistry\ValueObject\AttributeInfo;
use Cake\Core\Configure;

/**
 * Main registry for discovering and querying PHP attributes.
 *
 * Provides high-level API for attribute discovery and retrieval
 * with caching support and filtering capabilities.
 *
 * Can be used as a singleton or via dependency injection:
 *
 * ```php
 * // Singleton access (anywhere in your app)
 * $registry = AttributeRegistry::getInstance();
 *
 * // Via dependency injection (in controllers, commands, etc.)
 * public function index(AttributeRegistry $registry): Response
 * ```
 */
class AttributeRegistry
{
    private const REGISTRY_CACHE_KEY_ALL = 'attribute_registry_all';

    private static ?self $instance = null;

    /**
     * @var array<\AttributeRegistry\ValueObject\AttributeInfo>|null
     */
    private ?array $discoveredAttributes = null;

    /**
     * @param \AttributeRegistry\Service\AttributeScanner $scanner Scanner service
     * @param \AttributeRegistry\Service\AttributeCache $cache Cache service
     */
    public function __construct(
        private readonly AttributeScanner $scanner,
        private readonly AttributeCache $cache,
    ) {
    }

    /**
     * Get the singleton instance of AttributeRegistry.
     *
     * Creates and configures the instance from application config on first call.
     */
    public static function getInstance(): self
    {
        if (!self::$instance instanceof AttributeRegistry) {
            self::$instance = self::createFromConfig();
        }

        return self::$instance;
    }

    /**
     * Set the singleton instance.
     *
     * Useful for testing or custom configuration.
     *
     * @param self|null $instance Instance to set, or null to reset
     */
    public static function setInstance(?self $instance): void
    {
        self::$instance = $instance;
    }

    /**
     * Create a new AttributeRegistry from application configuration.
     */
    private static function createFromConfig(): self
    {
        $config = (array)Configure::read('AttributeRegistry');
        $scannerConfig = (array)($config['scanner'] ?? []);
        $cacheConfig = (array)($config['cache'] ?? []);

        // Create PathResolver with lazy plugin path resolution
        // The callback is only invoked when scanning is needed (cache miss)
        $pathResolver = new PathResolver(
            ROOT,
            fn(): array => (new PluginPathResolver())->getEnabledPluginPaths(),
        );

        $cache = new AttributeCache(
            (string)($cacheConfig['config'] ?? 'default'),
            (bool)($cacheConfig['enabled'] ?? true),
        );
        $parser = new AttributeParser(
            (array)($scannerConfig['exclude_attributes'] ?? []),
        );

        $scanner = new AttributeScanner(
            $parser,
            $pathResolver,
            [
                'paths' => (array)($scannerConfig['paths'] ?? ['src/**/*.php']),
                'exclude_paths' => (array)($scannerConfig['exclude_paths'] ?? ['vendor/**', 'tmp/**']),
            ],
        );

        return new self($scanner, $cache);
    }

    /**
     * Discover all attributes from configured paths.
     *
     * Returns an AttributeCollection for fluent filtering with domain-specific
     * methods while retaining all standard CakePHP Collection operations.
     *
     * Results are cached in memory for subsequent calls within the same request.
     *
     * Example:
     * ```php
     * // Using custom filter methods
     * $registry->discover()
     *     ->attribute(Route::class)
     *     ->namespace('App\\Controller\\*')
     *     ->targetType(AttributeTargetType::METHOD)
     *     ->toList();
     *
     * // Using standard Collection methods
     * $registry->discover()
     *     ->filter(fn($attr) => $attr->arguments['method'] === 'POST')
     *     ->groupBy(fn($attr) => $attr->className)
     *     ->toArray();
     * ```
     */
    public function discover(): AttributeCollection
    {
        if ($this->discoveredAttributes !== null) {
            return new AttributeCollection($this->discoveredAttributes);
        }

        /** @var array<array<string, mixed>>|null $cached */
        $cached = $this->cache->get(self::REGISTRY_CACHE_KEY_ALL);
        if ($cached !== null) {
            $this->discoveredAttributes = $this->hydrateFromCache($cached);

            return new AttributeCollection($this->discoveredAttributes);
        }

        $attributes = [];
        foreach ($this->scanner->scanAll() as $attribute) {
            $attributes[] = $attribute;
        }

        $this->cache->set(self::REGISTRY_CACHE_KEY_ALL, $this->serializeForCache($attributes));
        $this->discoveredAttributes = $attributes;

        return new AttributeCollection($attributes);
    }

    /**
     * Find attributes by attribute class name.
     *
     * @param string $attributeName Full or partial attribute class name
     * @return array<\AttributeRegistry\ValueObject\AttributeInfo>
     */
    public function findByAttribute(string $attributeName): array
    {
        return $this->discover()
            ->attributeContains($attributeName)
            ->toList();
    }

    /**
     * Find attributes by the class they are attached to.
     *
     * @param string $className Full or partial class name
     * @return array<\AttributeRegistry\ValueObject\AttributeInfo>
     */
    public function findByClass(string $className): array
    {
        return $this->discover()
            ->classNameContains($className)
            ->toList();
    }

    /**
     * Find attributes by target type (class, method, property, etc.).
     *
     * @param \AttributeRegistry\Enum\AttributeTargetType $type Target type to filter by
     * @return array<\AttributeRegistry\ValueObject\AttributeInfo>
     */
    public function findByTargetType(AttributeTargetType $type): array
    {
        return $this->discover()
            ->targetType($type)
            ->toList();
    }

    /**
     * Clear all cached attribute data.
     *
     * Clears atomic cache and legacy context-specific caches for backward compatibility.
     *
     * @return bool True on success
     */
    public function clearCache(): bool
    {
        $this->discoveredAttributes = null;

        // Clear atomic cache key
        $result = $this->cache->delete(self::REGISTRY_CACHE_KEY_ALL);

        // Clear legacy context-specific cache keys for backward compatibility
        $this->cache->delete('attribute_registry_web');
        $this->cache->delete('attribute_registry_cli');

        return $result;
    }

    /**
     * Check if caching is enabled.
     *
     * @return bool Whether caching is enabled
     */
    public function isCacheEnabled(): bool
    {
        return $this->cache->isEnabled();
    }

    /**
     * Warm the cache by discovering all attributes.
     *
     * @return bool True on success
     */
    public function warmCache(): bool
    {
        $this->clearCache();
        $this->discover();

        return true;
    }

    /**
     * Serialize attributes for cache storage.
     *
     * @param array<\AttributeRegistry\ValueObject\AttributeInfo> $attributes Attributes to serialize
     * @return array<array<string, mixed>>
     */
    private function serializeForCache(array $attributes): array
    {
        return array_map(
            fn(AttributeInfo $attr): array => $attr->toArray(),
            $attributes,
        );
    }

    /**
     * Hydrate attributes from cached data.
     *
     * @param array<array<string, mixed>> $cached Cached attribute data
     * @return array<\AttributeRegistry\ValueObject\AttributeInfo>
     */
    private function hydrateFromCache(array $cached): array
    {
        return array_map(
            fn(array $data): AttributeInfo => AttributeInfo::fromArray($data),
            $cached,
        );
    }
}
