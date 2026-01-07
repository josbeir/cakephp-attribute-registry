<?php
declare(strict_types=1);

namespace AttributeRegistry\Test\TestCase\Event;

use AttributeRegistry\AttributeRegistry;
use AttributeRegistry\Collection\AttributeCollection;
use AttributeRegistry\Event\AfterScanEvent;
use Cake\TestSuite\TestCase;

class AfterScanEventTest extends TestCase
{
    public function testEventConstant(): void
    {
        $this->assertSame('AttributeRegistry.afterScan', AfterScanEvent::NAME);
    }

    public function testEventCreationWithCollection(): void
    {
        $registry = $this->createStub(AttributeRegistry::class);
        $collection = new AttributeCollection([]);

        $event = new AfterScanEvent($registry, $collection);

        $this->assertSame(AfterScanEvent::NAME, $event->getName());
        $this->assertSame($registry, $event->getSubject());
        $this->assertSame($collection, $event->getAttributes());
    }
}
