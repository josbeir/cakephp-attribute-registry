<?php
declare(strict_types=1);

namespace AttributeRegistry\Service;

use Cake\Cache\Cache;

class AttributeCache
{
    /**
     * Constructor for AttributeCache.
     *
     * @param string $cacheConfig Cache configuration name
     * @param bool $enabled Whether caching is enabled
     */
    public function __construct(
        private string $cacheConfig = 'default',
        private bool $enabled = true,
    ) {
    }

    /**
     * Check if caching is enabled.
     *
     * @return bool Whether caching is enabled
     */
    public function isEnabled(): bool
    {
        return $this->enabled;
    }

    /**
     * Get cached data by key.
     *
     * @param string $key Cache key
     * @return array<mixed>|null Cached data or null if not found
     */
    public function get(string $key): ?array
    {
        if (!$this->enabled) {
            return null;
        }

        $result = Cache::read($key, $this->cacheConfig);

        return $result === false ? null : $result;
    }

    /**
     * Set data in cache.
     *
     * @param string $key Cache key
     * @param array<mixed> $data Data to cache
     * @param int|null $duration Cache duration in seconds (ignored for simplicity)
     * @return bool Success status
     */
    public function set(string $key, array $data, ?int $duration = null): bool
    {
        if (!$this->enabled) {
            return false;
        }

        // For simplicity, ignore custom duration for now
        // In a full implementation, you would need to handle different cache engines
        return Cache::write($key, $data, $this->cacheConfig);
    }

    /**
     * Delete cached data by key.
     *
     * @param string $key Cache key
     * @return bool Success status
     */
    public function delete(string $key): bool
    {
        if (!$this->enabled) {
            return false;
        }

        return Cache::delete($key, $this->cacheConfig);
    }

    /**
     * Clear all cached data in this cache configuration.
     *
     * @return bool Success status
     */
    public function clear(): bool
    {
        if (!$this->enabled) {
            return false;
        }

        return Cache::clear($this->cacheConfig);
    }
}
