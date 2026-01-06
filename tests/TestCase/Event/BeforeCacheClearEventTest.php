<?php
declare(strict_types=1);

namespace AttributeRegistry\Test\TestCase\Event;

use AttributeRegistry\AttributeRegistry;
use AttributeRegistry\Event\BeforeCacheClearEvent;
use Cake\TestSuite\TestCase;

class BeforeCacheClearEventTest extends TestCase
{
    public function testEventConstant(): void
    {
        $this->assertSame('AttributeRegistry.beforeCacheClear', BeforeCacheClearEvent::NAME);
    }

    public function testEventCreation(): void
    {
        $registry = $this->createMock(AttributeRegistry::class);
        $event = new BeforeCacheClearEvent($registry);

        $this->assertSame(BeforeCacheClearEvent::NAME, $event->getName());
        $this->assertSame($registry, $event->getSubject());
    }
}
