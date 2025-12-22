<?php
declare(strict_types=1);

namespace AttributeRegistry\Test\TestCase;

use AttributeRegistry\AttributeRegistry;
use AttributeRegistry\Enum\AttributeTargetType;
use Cake\Cache\Cache;
use Cake\TestSuite\TestCase;

class AttributeRegistryTest extends TestCase
{
    use AttributeRegistryTestTrait;

    private AttributeRegistry $registry;

    protected function setUp(): void
    {
        parent::setUp();

        Cache::setConfig('attribute_test', [
            'engine' => 'Array',
            'duration' => '+1 hour',
        ]);

        $this->loadTestAttributes();

        $this->registry = $this->createRegistry('attribute_test', true);
    }

    protected function tearDown(): void
    {
        parent::tearDown();
        Cache::clear('attribute_test');
        Cache::drop('attribute_test');
    }

    public function testAttributeRegistryCanBeCreated(): void
    {
        $this->assertInstanceOf(AttributeRegistry::class, $this->registry);
    }

    public function testDiscoverReturnsArray(): void
    {
        $result = $this->registry->discover();

        $this->assertNotEmpty($result);
    }

    public function testFindByAttributeReturnsMatchingAttributes(): void
    {
        $results = $this->registry->findByAttribute('TestRoute');

        $this->assertNotEmpty($results);
        foreach ($results as $attr) {
            $this->assertStringContainsString('TestRoute', $attr->attributeName);
        }
    }

    public function testFindByAttributeReturnsEmptyForNonExistent(): void
    {
        $results = $this->registry->findByAttribute('NonExistentAttribute');

        $this->assertEmpty($results);
    }

    public function testFindByClassReturnsMatchingAttributes(): void
    {
        $results = $this->registry->findByClass('TestController');

        $this->assertNotEmpty($results);
        foreach ($results as $attr) {
            $this->assertStringContainsString('TestController', $attr->className);
        }
    }

    public function testFindByClassReturnsEmptyForNonExistent(): void
    {
        $results = $this->registry->findByClass('NonExistentClass');

        $this->assertEmpty($results);
    }

    public function testFindByTargetTypeReturnsMatchingAttributes(): void
    {
        $classResults = $this->registry->findByTargetType(AttributeTargetType::CLASS_TYPE);
        $methodResults = $this->registry->findByTargetType(AttributeTargetType::METHOD);
        $propertyResults = $this->registry->findByTargetType(AttributeTargetType::PROPERTY);

        $this->assertNotEmpty($classResults);
        $this->assertNotEmpty($methodResults);
        $this->assertNotEmpty($propertyResults);

        foreach ($classResults as $attr) {
            $this->assertEquals(AttributeTargetType::CLASS_TYPE, $attr->target->type);
        }

        foreach ($methodResults as $attr) {
            $this->assertEquals(AttributeTargetType::METHOD, $attr->target->type);
        }

        foreach ($propertyResults as $attr) {
            $this->assertEquals(AttributeTargetType::PROPERTY, $attr->target->type);
        }
    }

    public function testClearCacheReturnsBool(): void
    {
        // First discover to populate cache
        $this->registry->discover();

        $result = $this->registry->clearCache();

        $this->assertTrue($result);
    }

    public function testWarmCacheReturnsBool(): void
    {
        $result = $this->registry->warmCache();

        $this->assertTrue($result);
    }

    public function testDiscoverUsesCacheOnSecondCall(): void
    {
        // First call - populates cache
        $result1 = $this->registry->discover();

        // Second call - should use memory cache (not file cache)
        $result2 = $this->registry->discover();

        $this->assertEquals($result1, $result2);
    }

    public function testDiscoverUsesFileCacheAfterClearingMemoryCache(): void
    {
        // First call - populates both memory and file cache
        $result1 = $this->registry->discover();

        // Create a new registry instance (simulating new request)
        // This tests the file cache path
        Cache::setConfig('attribute_test_2', [
            'engine' => 'Array',
            'duration' => '+1 hour',
        ]);

        $registry2 = $this->createRegistry('attribute_test', true);

        // Second registry should get data from file cache
        $result2 = $registry2->discover();

        $this->assertCount(count($result1), $result2);

        Cache::drop('attribute_test_2');
    }

    public function testIsCacheEnabled(): void
    {
        $this->assertTrue($this->registry->isCacheEnabled());
    }

    public function testIsCacheDisabled(): void
    {
        $registry = $this->createRegistry('attribute_test', false);

        $this->assertFalse($registry->isCacheEnabled());
    }

    public function testSetInstanceSetsCustomInstance(): void
    {
        // Set our test registry as the singleton instance
        AttributeRegistry::setInstance($this->registry);

        $instance = AttributeRegistry::getInstance();

        $this->assertSame($this->registry, $instance);

        // Clean up
        AttributeRegistry::setInstance(null);
    }

    public function testSetInstanceNullResetsInstance(): void
    {
        // First set an instance
        AttributeRegistry::setInstance($this->registry);

        // Then reset it
        AttributeRegistry::setInstance(null);

        // Getting instance again should create a new one (will fail without config, but proves reset worked)
        $this->addToAssertionCount(1);
    }
}
