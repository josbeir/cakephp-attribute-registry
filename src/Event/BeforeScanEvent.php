<?php
declare(strict_types=1);

namespace AttributeRegistry\Event;

use AttributeRegistry\AttributeRegistry;
use Cake\Event\Event;

/**
 * Event fired before scanning files for attributes.
 *
 * This event is dispatched only when attributes need to be scanned from source files,
 * which occurs when:
 * - No memory cache exists (first discovery in the request)
 * - No disk cache exists OR cache is disabled
 *
 * This event does NOT fire when results are served from cache.
 *
 * Typical Use Cases:
 * - Start scan-specific performance timers
 * - Log when actual file scanning occurs (vs cache hits)
 * - Monitor scan frequency to optimize cache strategy
 * - Prepare resources needed for file scanning
 *
 * @extends \Cake\Event\Event<\AttributeRegistry\AttributeRegistry>
 */
class BeforeScanEvent extends Event
{
    /**
     * Event name constant
     */
    public const NAME = 'AttributeRegistry.beforeScan';

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
