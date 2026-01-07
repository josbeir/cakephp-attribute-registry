<?php
declare(strict_types=1);

namespace AttributeRegistry\Test\TestCase\Event;

use AttributeRegistry\AttributeRegistry;
use AttributeRegistry\Event\BeforeDiscoverEvent;
use Cake\TestSuite\TestCase;

class BeforeDiscoverEventTest extends TestCase
{
    public function testEventConstant(): void
    {
        $this->assertSame('AttributeRegistry.beforeDiscover', BeforeDiscoverEvent::NAME);
    }

    public function testEventCreation(): void
    {
        $registry = $this->createStub(AttributeRegistry::class);
        $event = new BeforeDiscoverEvent($registry);

        $this->assertSame(BeforeDiscoverEvent::NAME, $event->getName());
        $this->assertSame($registry, $event->getSubject());
    }

    public function testGetSubjectReturnsTypedRegistry(): void
    {
        $registry = $this->createStub(AttributeRegistry::class);
        $event = new BeforeDiscoverEvent($registry);

        $subject = $event->getSubject();
        $this->assertInstanceOf(AttributeRegistry::class, $subject);
    }
}
