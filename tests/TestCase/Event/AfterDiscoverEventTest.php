<?php
declare(strict_types=1);

namespace AttributeRegistry\Test\TestCase\Event;

use AttributeRegistry\AttributeRegistry;
use AttributeRegistry\Collection\AttributeCollection;
use AttributeRegistry\Event\AfterDiscoverEvent;
use Cake\TestSuite\TestCase;

class AfterDiscoverEventTest extends TestCase
{
    public function testEventConstant(): void
    {
        $this->assertSame('AttributeRegistry.afterDiscover', AfterDiscoverEvent::NAME);
    }

    public function testEventCreationWithCollection(): void
    {
        $registry = $this->createStub(AttributeRegistry::class);
        $collection = new AttributeCollection([]);

        $event = new AfterDiscoverEvent($registry, $collection);

        $this->assertSame(AfterDiscoverEvent::NAME, $event->getName());
        $this->assertSame($registry, $event->getSubject());
        $this->assertSame($collection, $event->getAttributes());
    }

    public function testGetAttributesReturnsCollection(): void
    {
        $registry = $this->createStub(AttributeRegistry::class);
        $collection = new AttributeCollection([]);

        $event = new AfterDiscoverEvent($registry, $collection);

        $attributes = $event->getAttributes();
        $this->assertInstanceOf(AttributeCollection::class, $attributes);
        $this->assertSame($collection, $attributes);
    }
}
