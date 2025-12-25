<?php
declare(strict_types=1);

namespace AttributeRegistry\Test\TestCase;

use AttributeRegistry\Event\AttributeRegistryEvents;
use Cake\Event\EventList;
use Cake\Event\EventManager;
use Cake\TestSuite\TestCase;

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
            $this->rrmdir($this->cachePath);
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
            AttributeRegistryEvents::BEFORE_DISCOVER,
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
            AttributeRegistryEvents::AFTER_DISCOVER,
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
            AttributeRegistryEvents::AFTER_DISCOVER,
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

        // Should have both before and after
        $this->assertSame(2, $count);
    }

    private function rrmdir(string $dir): void
    {
        if (is_dir($dir)) {
            $objects = scandir($dir);
            foreach ($objects as $object) {
                if ($object !== '.' && $object !== '..') {
                    $path = $dir . DS . $object;
                    if (is_dir($path)) {
                        $this->rrmdir($path);
                    } else {
                        unlink($path);
                    }
                }
            }

            rmdir($dir);
        }
    }
}
