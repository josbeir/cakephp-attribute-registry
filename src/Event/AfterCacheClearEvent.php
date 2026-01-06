<?php
declare(strict_types=1);

namespace AttributeRegistry\Event;

use AttributeRegistry\AttributeRegistry;
use Cake\Event\Event;

/**
 * Event fired after cache has been cleared.
 *
 * This event is dispatched after the attribute cache has been cleared.
 *
 * Typical Use Cases:
 * - Log cache clear results
 * - Trigger cache warming operations
 * - Update metrics about cache operations
 * - Notify dependent systems about cache invalidation
 *
 * @extends \Cake\Event\Event<\AttributeRegistry\AttributeRegistry>
 */
class AfterCacheClearEvent extends Event
{
    /**
     * Event name constant
     */
    public const NAME = 'AttributeRegistry.afterCacheClear';

    /**
     * Constructor
     *
     * @param \AttributeRegistry\AttributeRegistry $subject The AttributeRegistry instance
     * @param bool $cleared Whether the cache was successfully cleared
     */
    public function __construct(AttributeRegistry $subject, private readonly bool $cleared)
    {
        parent::__construct(self::NAME, $subject, ['cleared' => $cleared]);
    }

    /**
     * Returns the AttributeRegistry subject
     *
     * @return \AttributeRegistry\AttributeRegistry
     */
    public function getSubject(): AttributeRegistry
    {
        return parent::getSubject();
    }

    /**
     * Check if the cache was successfully cleared
     */
    public function wasCleared(): bool
    {
        return $this->cleared;
    }
}
