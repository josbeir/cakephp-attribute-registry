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
     * Prevent instantiation
     */
    private function __construct()
    {
    }
}
