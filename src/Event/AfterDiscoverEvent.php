<?php
declare(strict_types=1);

namespace AttributeRegistry\Event;

use AttributeRegistry\AttributeRegistry;
use AttributeRegistry\Collection\AttributeCollection;
use Cake\Event\Event;

/**
 * Event fired after attribute discovery completes.
 *
 * This event is dispatched at the end of every discover() call after the AttributeCollection
 * has been created, regardless of the source (memory cache, disk cache, or fresh scan).
 *
 * Typical Use Cases:
 * - Complete performance timers started in beforeDiscover
 * - Log discovery results and statistics
 * - Post-process or validate discovered attributes
 * - Trigger dependent cache warming operations
 * - Update metrics dashboards with attribute counts
 *
 * @extends \Cake\Event\Event<\AttributeRegistry\AttributeRegistry>
 */
class AfterDiscoverEvent extends Event
{
    /**
     * Event name constant
     */
    public const NAME = 'AttributeRegistry.afterDiscover';

    /**
     * Constructor
     *
     * @param \AttributeRegistry\AttributeRegistry $subject The AttributeRegistry instance
     * @param \AttributeRegistry\Collection\AttributeCollection $attributes The discovered attributes collection
     */
    public function __construct(AttributeRegistry $subject, private readonly AttributeCollection $attributes)
    {
        parent::__construct(self::NAME, $subject, ['attributes' => $attributes]);
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
     * Get the discovered attributes collection
     *
     * @return \AttributeRegistry\Collection\AttributeCollection
     */
    public function getAttributes(): AttributeCollection
    {
        return $this->attributes;
    }
}
