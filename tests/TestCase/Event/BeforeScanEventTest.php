<?php
declare(strict_types=1);

namespace AttributeRegistry\Test\TestCase\Event;

use AttributeRegistry\AttributeRegistry;
use AttributeRegistry\Event\BeforeScanEvent;
use Cake\TestSuite\TestCase;

class BeforeScanEventTest extends TestCase
{
    public function testEventConstant(): void
    {
        $this->assertSame('AttributeRegistry.beforeScan', BeforeScanEvent::NAME);
    }

    public function testEventCreation(): void
    {
        $registry = $this->createStub(AttributeRegistry::class);
        $event = new BeforeScanEvent($registry);

        $this->assertSame(BeforeScanEvent::NAME, $event->getName());
        $this->assertSame($registry, $event->getSubject());
    }
}
