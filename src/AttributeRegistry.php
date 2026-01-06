<?php
declare(strict_types=1);

namespace AttributeRegistry;

use AttributeRegistry\Collection\AttributeCollection;
use AttributeRegistry\Enum\AttributeTargetType;
use AttributeRegistry\Event\AttributeRegistryEvents;
use AttributeRegistry\Service\AttributeParser;
use AttributeRegistry\Service\AttributeScanner;
use AttributeRegistry\Service\CompiledCache;
use AttributeRegistry\Service\PathResolver;
use AttributeRegistry\Service\PluginLocator;
use Cake\Core\Configure;
use Cake\Event\EventDispatcherInterface;
use Cake\Event\EventDispatcherTrait;

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
 *
 * @implements \Cake\Event\EventDispatcherInterface<\AttributeRegistry\AttributeRegistry>
 */
class AttributeRegistry implements EventDispatcherInterface
{
    /** @use \Cake\Event\EventDispatcherTrait<\AttributeRegistry\AttributeRegistry> */
    use EventDispatcherTrait;

    private const CACHE_KEY = 'attribute_registry';

    private static ?self $instance = null;

    /**
     * @var array<\AttributeRegistry\ValueObject\AttributeInfo>|null
     */
    private ?array $discoveredAttributes = null;

    /**
     * @param \AttributeRegistry\Service\AttributeScanner $scanner Scanner service
     * @param \AttributeRegistry\Service\CompiledCache $cache Cache service
     */
    public function __construct(
        private readonly AttributeScanner $scanner,
        private readonly CompiledCache $cache,
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

        $pluginLocator = new PluginLocator();
        $pathResolver = new PathResolver(ROOT, $pluginLocator);

        $cachePath = (string)($cacheConfig['path'] ?? CACHE . 'attribute_registry' . DS);
        $cache = new CompiledCache(
            $cachePath,
            (bool)($cacheConfig['enabled'] ?? true),
            (bool)($cacheConfig['validateFiles'] ?? false),
        );
        $parser = new AttributeParser(
            (array)($scannerConfig['exclude_attributes'] ?? []),
            $pluginLocator,
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
        // Dispatch before discover event
        $this->dispatchEvent(AttributeRegistryEvents::BEFORE_DISCOVER);

        if ($this->discoveredAttributes !== null) {
            $collection = new AttributeCollection($this->discoveredAttributes);
            $this->dispatchAfterDiscover($collection);

            return $collection;
        }

        /** @var array<\AttributeRegistry\ValueObject\AttributeInfo>|null $cached */
        $cached = $this->cache->get(self::CACHE_KEY);
        if ($cached !== null) {
            $this->discoveredAttributes = $cached;
            $collection = new AttributeCollection($this->discoveredAttributes);
            $this->dispatchAfterDiscover($collection);

            return $collection;
        }

        // Dispatch before scan event
        $this->dispatchEvent(AttributeRegistryEvents::BEFORE_SCAN);

        $attributes = [];
        foreach ($this->scanner->scanAll() as $attribute) {
            $attributes[] = $attribute;
        }

        $this->cache->set(self::CACHE_KEY, $attributes);
        $this->discoveredAttributes = $attributes;

        $collection = new AttributeCollection($attributes);

        // Dispatch after scan event
        $this->dispatchEvent(AttributeRegistryEvents::AFTER_SCAN, [
            'attributes' => $collection,
        ]);

        $this->dispatchAfterDiscover($collection);

        return $collection;
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
     * @return bool True on success
     */
    public function clearCache(): bool
    {
        $this->dispatchEvent(AttributeRegistryEvents::BEFORE_CACHE_CLEAR);

        $this->discoveredAttributes = null;
        $success = $this->cache->delete(self::CACHE_KEY);

        $this->dispatchEvent(AttributeRegistryEvents::AFTER_CACHE_CLEAR, [
            'success' => $success,
        ]);

        return $success;
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
     * Dispatch the after discover event with the collection.
     *
     * @param \AttributeRegistry\Collection\AttributeCollection $collection The discovered attributes collection
     */
    private function dispatchAfterDiscover(AttributeCollection $collection): void
    {
        $this->dispatchEvent(AttributeRegistryEvents::AFTER_DISCOVER, [
            'attributes' => $collection,
        ]);
    }
}
