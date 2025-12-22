<?php
declare(strict_types=1);

namespace AttributeRegistry;

use AttributeRegistry\Enum\AttributeTargetType;
use AttributeRegistry\Service\AttributeCache;
use AttributeRegistry\Service\AttributeParser;
use AttributeRegistry\Service\AttributeScanner;
use AttributeRegistry\Service\PathResolver;
use AttributeRegistry\ValueObject\AttributeInfo;
use Cake\Core\Configure;
use Cake\Core\Plugin;

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
    private const REGISTRY_CACHE_KEY = 'attribute_registry_all';

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

        $pathResolver = new PathResolver(implode(PATH_SEPARATOR, self::resolveAllPaths()));
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
                'max_file_size' => (int)($scannerConfig['max_file_size'] ?? 1024 * 1024),
            ],
        );

        return new self($scanner, $cache);
    }

    /**
     * Resolve all base paths from app + loaded plugins.
     *
     * @return array<string> Resolved base paths
     */
    private static function resolveAllPaths(): array
    {
        $basePaths = [];
        $basePaths[] = ROOT;

        $plugins = Plugin::getCollection();
        foreach ($plugins as $plugin) {
            $basePaths[] = $plugin->getPath();
        }

        return $basePaths;
    }

    /**
     * Discover all attributes from configured paths.
     *
     * Results are cached in memory for subsequent calls within the same request.
     *
     * @return array<\AttributeRegistry\ValueObject\AttributeInfo>
     */
    public function discover(): array
    {
        if ($this->discoveredAttributes !== null) {
            return $this->discoveredAttributes;
        }

        /** @var array<array<string, mixed>>|null $cached */
        $cached = $this->cache->get(self::REGISTRY_CACHE_KEY);
        if ($cached !== null) {
            $this->discoveredAttributes = $this->hydrateFromCache($cached);

            return $this->discoveredAttributes;
        }

        $attributes = [];
        foreach ($this->scanner->scanAll() as $attribute) {
            $attributes[] = $attribute;
        }

        $this->cache->set(self::REGISTRY_CACHE_KEY, $this->serializeForCache($attributes));
        $this->discoveredAttributes = $attributes;

        return $attributes;
    }

    /**
     * Find attributes by attribute class name.
     *
     * @param string $attributeName Full or partial attribute class name
     * @return array<\AttributeRegistry\ValueObject\AttributeInfo>
     */
    public function findByAttribute(string $attributeName): array
    {
        $all = $this->discover();

        return array_values(array_filter(
            $all,
            fn(AttributeInfo $attr): bool => str_contains($attr->attributeName, $attributeName),
        ));
    }

    /**
     * Find attributes by the class they are attached to.
     *
     * @param string $className Full or partial class name
     * @return array<\AttributeRegistry\ValueObject\AttributeInfo>
     */
    public function findByClass(string $className): array
    {
        $all = $this->discover();

        return array_values(array_filter(
            $all,
            fn(AttributeInfo $attr): bool => str_contains($attr->className, $className),
        ));
    }

    /**
     * Find attributes by target type (class, method, property, etc.).
     *
     * @param \AttributeRegistry\Enum\AttributeTargetType $type Target type to filter by
     * @return array<\AttributeRegistry\ValueObject\AttributeInfo>
     */
    public function findByTargetType(AttributeTargetType $type): array
    {
        $all = $this->discover();

        return array_values(array_filter(
            $all,
            fn(AttributeInfo $attr): bool => $attr->target->type === $type,
        ));
    }

    /**
     * Clear all cached attribute data.
     *
     * @return bool True on success
     */
    public function clearCache(): bool
    {
        $this->discoveredAttributes = null;

        return $this->cache->clear();
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
