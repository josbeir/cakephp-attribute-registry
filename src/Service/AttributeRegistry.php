<?php
declare(strict_types=1);

namespace AttributeRegistry\Service;

use AttributeRegistry\Enum\AttributeTargetType;
use AttributeRegistry\ValueObject\AttributeInfo;

/**
 * Main registry service for discovering and querying PHP attributes.
 *
 * Provides high-level API for attribute discovery and retrieval
 * with caching support and filtering capabilities.
 */
class AttributeRegistry
{
    private const REGISTRY_CACHE_KEY = 'attribute_registry_all';

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
