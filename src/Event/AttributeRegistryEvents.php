<?php
declare(strict_types=1);

namespace AttributeRegistry\Event;

/**
 * Event names dispatched by AttributeRegistry.
 *
 * The AttributeRegistry dispatches events at key points in the discovery and caching lifecycle.
 * All events use the subject object (AttributeRegistry instance) and can access it via
 * $event->getSubject().
 */
final class AttributeRegistryEvents
{
    /**
     * Fired before attribute discovery starts.
     *
     * This event is dispatched at the very start of every discover() call, regardless of
     * whether the result will be served from memory cache, disk cache, or freshly scanned.
     *
     * Event Data: None
     *
     * Typical Use Cases:
     * - Start performance timers for monitoring discovery duration
     * - Log discovery requests for debugging
     * - Initialize contextual data for subsequent events
     * - Increment metrics counters
     *
     * Note: This event fires even when results are already cached in memory, making it
     * suitable for monitoring all discovery requests.
     */
    public const BEFORE_DISCOVER = 'AttributeRegistry.beforeDiscover';

    /**
     * Fired after attribute discovery completes.
     *
     * This event is dispatched at the end of every discover() call after the AttributeCollection
     * has been created, regardless of the source (memory cache, disk cache, or fresh scan).
     *
     * Event Data:
     * - 'attributes' (AttributeCollection): The discovered attributes collection with all
     *   filtering methods available. Use $collection->count() to get the total number.
     *
     * Typical Use Cases:
     * - Complete performance timers started in beforeDiscover
     * - Log discovery results and statistics
     * - Post-process or validate discovered attributes
     * - Trigger dependent cache warming operations
     * - Update metrics dashboards with attribute counts
     *
     * Note: This event provides the same collection regardless of cache source, making it
     * ideal for consistent post-processing logic.
     */
    public const AFTER_DISCOVER = 'AttributeRegistry.afterDiscover';

    /**
     * Fired before scanning files for attributes.
     *
     * This event is dispatched only when attributes need to be scanned from source files,
     * which occurs when:
     * - No memory cache exists (first discovery in the request)
     * - No disk cache exists OR cache is disabled
     *
     * This event does NOT fire when results are served from cache.
     *
     * Event Data: None
     *
     * Typical Use Cases:
     * - Start scan-specific performance timers
     * - Log when actual file scanning occurs (vs cache hits)
     * - Monitor scan frequency to optimize cache strategy
     * - Prepare resources needed for file scanning
     *
     * Relationship: Always followed by afterScan when fired. Always occurs between
     * beforeDiscover and afterDiscover events.
     */
    public const BEFORE_SCAN = 'AttributeRegistry.beforeScan';

    /**
     * Fired after scanning files for attributes completes.
     *
     * This event is dispatched only after successfully scanning source files for attributes.
     * It fires in the same conditions as beforeScan (no cache or cache disabled).
     *
     * Event Data:
     * - 'attributes' (AttributeCollection): The freshly scanned attributes collection before
     *   being cached. This is the same collection that will be passed to afterDiscover.
     *
     * Typical Use Cases:
     * - Complete scan-specific performance timers
     * - Log scan duration and file counts
     * - Monitor scanning performance degradation
     * - Trigger cache optimization based on scan metrics
     * - Alert on unexpectedly slow scans
     *
     * Note: This event provides insight into scan performance separate from cache operations.
     * Use this rather than afterDiscover when you specifically want to monitor file scanning.
     */
    public const AFTER_SCAN = 'AttributeRegistry.afterScan';

    /**
     * Fired before clearing the attribute cache.
     *
     * This event is dispatched at the start of clearCache() before any cache files are deleted.
     *
     * Event Data: None
     *
     * Typical Use Cases:
     * - Trigger clearing of related/dependent caches
     * - Log cache clearing operations for audit trails
     * - Backup current cache before clearing (if needed)
     * - Send notifications about cache invalidation
     *
     * Relationship: Always followed by afterCacheClear when clearCache() is called.
     */
    public const BEFORE_CACHE_CLEAR = 'AttributeRegistry.beforeCacheClear';

    /**
     * Fired after the attribute cache has been cleared.
     *
     * This event is dispatched at the end of clearCache() after attempting to delete cache files.
     *
     * Event Data:
     * - 'success' (bool): Whether the cache was successfully cleared. True indicates all cache
     *   files were deleted. False indicates a failure occurred during deletion.
     *
     * Typical Use Cases:
     * - Verify cache clearing completed successfully
     * - Log cache clearing results
     * - Clear related caches only if AttributeRegistry cache cleared successfully
     * - Trigger cache warming operations after successful clear
     * - Alert on cache clearing failures
     *
     * Example:
     * ```php
     * EventManager::instance()->on(
     *     AttributeRegistryEvents::AFTER_CACHE_CLEAR,
     *     function (EventInterface $event) {
     *         if ($event->getData('success')) {
     *             Cache::delete('my_route_cache');
     *             Cache::delete('api_documentation');
     *         }
     *     }
     * );
     * ```
     */
    public const AFTER_CACHE_CLEAR = 'AttributeRegistry.afterCacheClear';

    /**
     * Prevent instantiation
     */
    private function __construct()
    {
    }
}
