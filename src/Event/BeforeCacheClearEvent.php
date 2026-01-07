<?php
declare(strict_types=1);

namespace AttributeRegistry\Event;

use AttributeRegistry\AttributeRegistry;
use Cake\Event\Event;

/**
 * Event fired before cache is cleared.
 *
 * This event is dispatched before the attribute cache is cleared.
 *
 * Typical Use Cases:
 * - Log cache clear operations
 * - Perform cleanup before cache is removed
 * - Notify dependent systems about upcoming cache invalidation
 *
 * @extends \Cake\Event\Event<\AttributeRegistry\AttributeRegistry>
 */
class BeforeCacheClearEvent extends Event
{
    /**
     * Event name constant
     */
    public const NAME = 'AttributeRegistry.beforeCacheClear';

    /**
     * Constructor
     *
     * @param \AttributeRegistry\AttributeRegistry $subject The AttributeRegistry instance
     */
    public function __construct(AttributeRegistry $subject)
    {
        parent::__construct(self::NAME, $subject);
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
}
