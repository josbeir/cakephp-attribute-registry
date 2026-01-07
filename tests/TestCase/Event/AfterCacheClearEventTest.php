<?php
declare(strict_types=1);

namespace AttributeRegistry\Test\TestCase\Event;

use AttributeRegistry\AttributeRegistry;
use AttributeRegistry\Event\AfterCacheClearEvent;
use Cake\TestSuite\TestCase;

class AfterCacheClearEventTest extends TestCase
{
    public function testEventConstant(): void
    {
        $this->assertSame('AttributeRegistry.afterCacheClear', AfterCacheClearEvent::NAME);
    }

    public function testEventCreationWithResult(): void
    {
        $registry = $this->createMock(AttributeRegistry::class);
        $event = new AfterCacheClearEvent($registry, true);

        $this->assertSame(AfterCacheClearEvent::NAME, $event->getName());
        $this->assertSame($registry, $event->getSubject());
        $this->assertTrue($event->wasCleared());
    }

    public function testWasClearedReturnsFalse(): void
    {
        $registry = $this->createMock(AttributeRegistry::class);
        $event = new AfterCacheClearEvent($registry, false);

        $this->assertFalse($event->wasCleared());
    }
}
