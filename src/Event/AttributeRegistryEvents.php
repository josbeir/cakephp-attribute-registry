<?php
declare(strict_types=1);

namespace AttributeRegistry\Event;

/**
 * Event names dispatched by AttributeRegistry
 */
final class AttributeRegistryEvents
{
    /**
     * Fired before attribute discovery starts
     */
    public const BEFORE_DISCOVER = 'AttributeRegistry.beforeDiscover';

    /**
     * Fired after attribute discovery completes
     */
    public const AFTER_DISCOVER = 'AttributeRegistry.afterDiscover';

    /**
     * Fired before scanning files for attributes
     */
    public const BEFORE_SCAN = 'AttributeRegistry.beforeScan';

    /**
     * Fired after scanning files for attributes completes
     */
    public const AFTER_SCAN = 'AttributeRegistry.afterScan';

    /**
     * Fired before clearing the attribute cache
     */
    public const BEFORE_CACHE_CLEAR = 'AttributeRegistry.beforeCacheClear';

    /**
     * Fired after the attribute cache has been cleared
     */
    public const AFTER_CACHE_CLEAR = 'AttributeRegistry.afterCacheClear';

    /**
     * Prevent instantiation
     */
    private function __construct()
    {
    }
}
