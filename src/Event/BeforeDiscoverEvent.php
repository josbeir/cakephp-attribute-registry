<?php
declare(strict_types=1);

namespace AttributeRegistry\Event;

use AttributeRegistry\AttributeRegistry;
use Cake\Event\Event;

/**
 * Event fired before attribute discovery starts.
 *
 * This event is dispatched at the very start of every discover() call, regardless of
 * whether the result will be served from memory cache, disk cache, or freshly scanned.
 *
 * Typical Use Cases:
 * - Start performance timers for monitoring discovery duration
 * - Log discovery requests for debugging
 * - Initialize contextual data for subsequent events
 * - Increment metrics counters
 *
 * @extends \Cake\Event\Event<\AttributeRegistry\AttributeRegistry>
 */
class BeforeDiscoverEvent extends Event
{
    /**
     * Event name constant
     */
    public const NAME = 'AttributeRegistry.beforeDiscover';

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
