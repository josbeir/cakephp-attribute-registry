<?php
declare(strict_types=1);

namespace AttributeRegistry\Test\TestCase;

use AttributeRegistry\Collection\AttributeCollection;
use AttributeRegistry\Event\AfterCacheClearEvent;
use AttributeRegistry\Event\AfterDiscoverEvent;
use AttributeRegistry\Event\AfterScanEvent;
use AttributeRegistry\Event\BeforeCacheClearEvent;
use AttributeRegistry\Event\BeforeDiscoverEvent;
use AttributeRegistry\Event\BeforeScanEvent;
use Cake\Event\EventInterface;
use Cake\Event\EventList;
use Cake\Event\EventManager;
use Cake\TestSuite\TestCase;
use Cake\Utility\Filesystem;

class AttributeRegistryEventTest extends TestCase
{
    use AttributeRegistryTestTrait;

    private string $cachePath;

    protected function setUp(): void
    {
        parent::setUp();
        $this->cachePath = TMP . 'tests' . DS . 'event_test_' . uniqid();
    }

    protected function tearDown(): void
    {
        if (file_exists($this->cachePath)) {
            (new Filesystem())->deleteDir($this->cachePath);
        }

        parent::tearDown();
    }

    public function testBeforeDiscoverEventFired(): void
    {
        $registry = $this->createRegistry($this->cachePath, false);
        $eventManager = $registry->getEventManager();
        assert($eventManager instanceof EventManager);
        $eventManager->setEventList(new EventList());

        $registry->discover();

        $this->assertEventFired(
            BeforeDiscoverEvent::NAME,
            $eventManager,
        );
    }

    public function testAfterDiscoverEventFired(): void
    {
        $registry = $this->createRegistry($this->cachePath, false);
        $eventManager = $registry->getEventManager();
        assert($eventManager instanceof EventManager);
        $eventManager->setEventList(new EventList());

        $registry->discover();

        $this->assertEventFired(
            AfterDiscoverEvent::NAME,
            $eventManager,
        );
    }

    public function testAfterDiscoverIncludesAttributeCollection(): void
    {
        $registry = $this->createRegistry($this->cachePath, false);
        $eventManager = $registry->getEventManager();
        assert($eventManager instanceof EventManager);
        $eventManager->setEventList(new EventList());

        $result = $registry->discover();

        // Verify event was fired with collection
        $this->assertEventFiredWith(
            AfterDiscoverEvent::NAME,
            'attributes',
            $result,
            $eventManager,
        );
    }

    public function testBothEventsFireOnEachDiscover(): void
    {
        $registry = $this->createRegistry($this->cachePath, false);
        $eventManager = $registry->getEventManager();
        assert($eventManager instanceof EventManager);
        $eventManager->setEventList(new EventList());

        // First discover
        $registry->discover();

        // Count events
        $eventList = $eventManager->getEventList();
        assert($eventList instanceof EventList);
        $count = $eventList->count();

        // Should have beforeDiscover, beforeScan, afterScan, afterDiscover (4 events)
        $this->assertSame(4, $count);
    }

    public function testBeforeScanEventFired(): void
    {
        $registry = $this->createRegistry($this->cachePath, false);
        $eventManager = $registry->getEventManager();
        assert($eventManager instanceof EventManager);
        $eventManager->setEventList(new EventList());

        $registry->discover();

        $this->assertEventFired(
            BeforeScanEvent::NAME,
            $eventManager,
        );
    }

    public function testAfterScanEventFired(): void
    {
        $registry = $this->createRegistry($this->cachePath, false);
        $eventManager = $registry->getEventManager();
        assert($eventManager instanceof EventManager);
        $eventManager->setEventList(new EventList());

        $registry->discover();

        $this->assertEventFired(
            AfterScanEvent::NAME,
            $eventManager,
        );
    }

    public function testScanEventsNotFiredWhenCached(): void
    {
        $registry = $this->createRegistry($this->cachePath, true);
        $eventManager = $registry->getEventManager();
        assert($eventManager instanceof EventManager);

        // First discover to warm cache
        $registry->discover();

        // Reset event list
        $eventManager->setEventList(new EventList());

        // Second discover should use cache
        $registry->discover();

        // Scan events should NOT be fired
        $eventList = $eventManager->getEventList();
        assert($eventList instanceof EventList);

        $firedEvents = [];
        $eventCount = $eventList->count();
        for ($i = 0; $i < $eventCount; $i++) {
            $event = $eventList[$i];
            assert($event instanceof EventInterface);
            $firedEvents[] = $event->getName();
        }

        $this->assertNotContains(BeforeScanEvent::NAME, $firedEvents);
        $this->assertNotContains(AfterScanEvent::NAME, $firedEvents);
    }

    public function testBeforeCacheClearEventFired(): void
    {
        $registry = $this->createRegistry($this->cachePath, true);
        $eventManager = $registry->getEventManager();
        assert($eventManager instanceof EventManager);
        $eventManager->setEventList(new EventList());

        $registry->clearCache();

        $this->assertEventFired(
            BeforeCacheClearEvent::NAME,
            $eventManager,
        );
    }

    public function testAfterCacheClearEventFired(): void
    {
        $registry = $this->createRegistry($this->cachePath, true);
        $eventManager = $registry->getEventManager();
        assert($eventManager instanceof EventManager);
        $eventManager->setEventList(new EventList());

        $registry->clearCache();

        $this->assertEventFired(
            AfterCacheClearEvent::NAME,
            $eventManager,
        );
    }

    public function testAfterCacheClearIncludesSuccessFlag(): void
    {
        $registry = $this->createRegistry($this->cachePath, true);
        $eventManager = $registry->getEventManager();
        assert($eventManager instanceof EventManager);
        $eventManager->setEventList(new EventList());

        $registry->clearCache();

        $this->assertEventFiredWith(
            AfterCacheClearEvent::NAME,
            'cleared',
            true,
            $eventManager,
        );
    }

    public function testBeforeDiscoverEventIsTypedClass(): void
    {
        $registry = $this->createRegistry($this->cachePath, false);
        $fired = false;

        $registry->getEventManager()->on('AttributeRegistry.beforeDiscover', function (EventInterface $event) use (&$fired): void {
            $this->assertInstanceOf(BeforeDiscoverEvent::class, $event);
            $this->assertSame(BeforeDiscoverEvent::NAME, $event->getName());
            $fired = true;
        });

        $registry->discover();
        $this->assertTrue($fired, 'Event listener was not called');
    }

    public function testAfterDiscoverEventIsTypedClass(): void
    {
        $registry = $this->createRegistry($this->cachePath, false);
        $fired = false;

        $registry->getEventManager()->on('AttributeRegistry.afterDiscover', function (EventInterface $event) use (&$fired): void {
            $this->assertInstanceOf(AfterDiscoverEvent::class, $event);
            $this->assertInstanceOf(AttributeCollection::class, $event->getAttributes());
            $fired = true;
        });

        $registry->discover();
        $this->assertTrue($fired, 'Event listener was not called');
    }

    public function testBeforeScanEventIsTypedClass(): void
    {
        $registry = $this->createRegistry($this->cachePath, false);
        $fired = false;

        $registry->getEventManager()->on('AttributeRegistry.beforeScan', function (EventInterface $event) use (&$fired): void {
            $this->assertInstanceOf(BeforeScanEvent::class, $event);
            $this->assertSame(BeforeScanEvent::NAME, $event->getName());
            $fired = true;
        });

        $registry->discover();
        $this->assertTrue($fired, 'Event listener was not called');
    }

    public function testAfterScanEventIsTypedClass(): void
    {
        $registry = $this->createRegistry($this->cachePath, false);
        $fired = false;

        $registry->getEventManager()->on('AttributeRegistry.afterScan', function (EventInterface $event) use (&$fired): void {
            $this->assertInstanceOf(AfterScanEvent::class, $event);
            $this->assertInstanceOf(AttributeCollection::class, $event->getAttributes());
            $fired = true;
        });

        $registry->discover();
        $this->assertTrue($fired, 'Event listener was not called');
    }

    public function testBeforeCacheClearEventIsTypedClass(): void
    {
        $registry = $this->createRegistry($this->cachePath, true);
        $fired = false;

        $registry->getEventManager()->on('AttributeRegistry.beforeCacheClear', function (EventInterface $event) use (&$fired): void {
            $this->assertInstanceOf(BeforeCacheClearEvent::class, $event);
            $this->assertSame(BeforeCacheClearEvent::NAME, $event->getName());
            $fired = true;
        });

        $registry->clearCache();
        $this->assertTrue($fired, 'Event listener was not called');
    }

    public function testAfterCacheClearEventIsTypedClass(): void
    {
        $registry = $this->createRegistry($this->cachePath, true);
        $fired = false;

        $registry->getEventManager()->on('AttributeRegistry.afterCacheClear', function (EventInterface $event) use (&$fired): void {
            $this->assertInstanceOf(AfterCacheClearEvent::class, $event);
            $this->assertTrue($event->wasCleared());
            $fired = true;
        });

        $registry->clearCache();
        $this->assertTrue($fired, 'Event listener was not called');
    }
}
