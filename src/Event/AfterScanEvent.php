<?php
declare(strict_types=1);

namespace AttributeRegistry\Event;

use AttributeRegistry\AttributeRegistry;
use AttributeRegistry\Collection\AttributeCollection;
use Cake\Event\Event;

/**
 * Event fired after scanning files for attributes completes.
 *
 * This event is dispatched only after successfully scanning source files for attributes.
 * It fires in the same conditions as beforeScan (no cache or cache disabled).
 *
 * Typical Use Cases:
 * - Complete scan-specific performance timers
 * - Log scan duration and file counts
 * - Monitor scanning performance degradation
 * - Trigger cache optimization based on scan metrics
 * - Alert on unexpectedly slow scans
 *
 * @extends \Cake\Event\Event<\AttributeRegistry\AttributeRegistry>
 */
class AfterScanEvent extends Event
{
    /**
     * Event name constant
     */
    public const NAME = 'AttributeRegistry.afterScan';

    /**
     * Constructor
     *
     * @param \AttributeRegistry\AttributeRegistry $subject The AttributeRegistry instance
     * @param \AttributeRegistry\Collection\AttributeCollection $attributes The freshly scanned attributes collection
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
     * Get the freshly scanned attributes collection
     *
     * @return \AttributeRegistry\Collection\AttributeCollection
     */
    public function getAttributes(): AttributeCollection
    {
        return $this->attributes;
    }
}
