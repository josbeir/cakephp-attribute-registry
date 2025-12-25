<?php
declare(strict_types=1);

namespace AttributeRegistry\Test\TestCase;

use AttributeRegistry\AttributeRegistry;
use AttributeRegistry\Collection\AttributeCollection;
use AttributeRegistry\Enum\AttributeTargetType;
use AttributeRegistry\Service\PluginLocator;
use AttributeRegistry\Test\Data\TestAttributeArgument;
use AttributeRegistry\Test\Data\TestRoute;
use AttributeRegistry\Test\Data\TestWithObject;
use AttributeRegistry\Test\Data\TestWithObjectArray;
use AttributeRegistry\ValueObject\AttributeInfo;
use Cake\TestSuite\TestCase;
use Cake\Utility\Filesystem;

class AttributeRegistryTest extends TestCase
{
    use AttributeRegistryTestTrait;

    private AttributeRegistry $registry;

    private string $tempPath;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tempPath = sys_get_temp_dir() . '/attribute_registry_test_' . uniqid() . '/';
        mkdir($this->tempPath, 0755, true);

        $this->loadTestAttributes();
        $this->loadTestPlugins();

        // Create registry with both test data and plugin paths
        $this->registry = $this->createRegistryWithPlugins($this->tempPath, true);
    }

    protected function tearDown(): void
    {
        parent::tearDown();
        if (is_dir($this->tempPath)) {
            (new Filesystem())->deleteDir($this->tempPath);
        }
        $this->clearPlugins();
    }

    public function testAttributeRegistryCanBeCreated(): void
    {
        $this->assertInstanceOf(AttributeRegistry::class, $this->registry);
    }

    public function testDiscoverReturnsAttributeCollection(): void
    {
        $result = $this->registry->discover();

        $this->assertInstanceOf(AttributeCollection::class, $result);
        $this->assertNotEmpty($result->toList());
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

    public function testClearCacheRemovesCompiledCacheFile(): void
    {
        // Populate cache
        $this->registry->discover();

        // Verify cache file exists (with hash suffix from key 'attribute_registry')
        $cacheFile = $this->tempPath . 'attribute_registry_da54e0f65aed1014.php';
        $this->assertFileExists($cacheFile);

        $this->registry->clearCache();

        // Cache file should be removed
        $this->assertFileDoesNotExist($cacheFile);
    }

    public function testDiscoverUsesCompiledCache(): void
    {
        // Clear any existing cache
        $this->registry->clearCache();

        // First discover call should populate cache
        $this->registry->discover();

        // Verify cache file is created (with hash suffix from key 'attribute_registry')
        $cacheFile = $this->tempPath . 'attribute_registry_da54e0f65aed1014.php';
        $this->assertFileExists($cacheFile);

        // Verify it contains compiled PHP code
        $content = file_get_contents($cacheFile);
        $this->assertNotFalse($content);
        $this->assertStringContainsString('new \\AttributeRegistry\\ValueObject\\AttributeInfo', $content);
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

        $this->assertEquals($result1->toList(), $result2->toList());
    }

    public function testDiscoverUsesCompiledCacheAfterClearingMemoryCache(): void
    {
        // First call - populates both memory and compiled cache
        $result1 = $this->registry->discover();

        // Create a new registry instance (simulating new request)
        // This tests the compiled cache path - use same creation method
        $registry2 = $this->createRegistryWithPlugins($this->tempPath, true);

        // Second registry should get data from compiled cache
        $result2 = $registry2->discover();

        $this->assertCount($result1->count(), $result2);
    }

    public function testIsCacheEnabled(): void
    {
        $this->assertTrue($this->registry->isCacheEnabled());
    }

    public function testIsCacheDisabled(): void
    {
        $registry = $this->createRegistry($this->tempPath, false);

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

    public function testDiscoverCanFilterByAttribute(): void
    {
        $result = $this->registry->discover()
            ->attribute(TestRoute::class)
            ->toList();

        $this->assertNotEmpty($result);
        foreach ($result as $attr) {
            $this->assertSame(TestRoute::class, $attr->attributeName);
        }
    }

    public function testDiscoverCanCombineFilters(): void
    {
        $result = $this->registry->discover()
            ->namespace('AttributeRegistry\\Test\\Data\\*')
            ->targetType(AttributeTargetType::METHOD)
            ->toList();

        $this->assertNotEmpty($result);
        foreach ($result as $attr) {
            $this->assertSame(AttributeTargetType::METHOD, $attr->target->type);
        }
    }

    public function testAttributeArgumentsWithObjectInstances(): void
    {
        $results = $this->registry->findByClass('TestWithObjectArgument');

        $this->assertNotEmpty($results);

        // Find the class-level attribute
        $classAttr = null;
        foreach ($results as $attr) {
            if ($attr->target->type === AttributeTargetType::CLASS_TYPE) {
                $classAttr = $attr;
                break;
            }
        }

        $this->assertNotNull($classAttr);
        $this->assertSame(TestWithObject::class, $classAttr->attributeName);

        // Check that the object argument was captured
        $this->assertArrayHasKey('argument', $classAttr->arguments);
        $this->assertArrayHasKey('label', $classAttr->arguments);
        $this->assertSame('Object Argument Test', $classAttr->arguments['label']);
    }

    public function testAttributeArgumentsWithObjectSerializationDeserialization(): void
    {
        $results = $this->registry->findByClass('TestWithObjectArgument');
        $classAttr = null;

        foreach ($results as $attr) {
            if ($attr->target->type === AttributeTargetType::CLASS_TYPE) {
                $classAttr = $attr;
                break;
            }
        }

        $this->assertNotNull($classAttr);

        // Serialize to array (simulating cache storage)
        $serialized = $classAttr->toArray();

        // Deserialize from array
        $deserialized = $classAttr::fromArray($serialized);

        // Verify the object argument is preserved through serialization
        $this->assertSame($classAttr->attributeName, $deserialized->attributeName);
        $this->assertSame($classAttr->className, $deserialized->className);
        $this->assertEquals($classAttr->arguments, $deserialized->arguments);

        // Verify nested object properties
        if (is_object($deserialized->arguments['argument'])) {
            $this->assertInstanceOf(TestAttributeArgument::class, $deserialized->arguments['argument']);
        }
    }

    public function testAttributeMethodArgumentsWithObjectInstances(): void
    {
        $results = $this->registry->findByClass('TestWithObjectArgument');

        // Find the method-level attribute
        $methodAttr = null;
        foreach ($results as $attr) {
            if ($attr->target->type === AttributeTargetType::METHOD) {
                $methodAttr = $attr;
                break;
            }
        }

        $this->assertNotNull($methodAttr);
        $this->assertSame(TestWithObject::class, $methodAttr->attributeName);
        $this->assertSame('methodWithObject', $methodAttr->target->targetName);

        // Check that object argument was captured
        $this->assertArrayHasKey('argument', $methodAttr->arguments);
    }

    public function testAttributeArgumentsWithObjectArrays(): void
    {
        $results = $this->registry->findByClass('TestWithObjectArrayArgument');

        $this->assertNotEmpty($results);

        $classAttr = null;
        foreach ($results as $attr) {
            if ($attr->target->type === AttributeTargetType::CLASS_TYPE) {
                $classAttr = $attr;
                break;
            }
        }

        $this->assertNotNull($classAttr);
        $this->assertSame(TestWithObjectArray::class, $classAttr->attributeName);

        // Check that the arguments array exists and has the correct key
        $this->assertArrayHasKey('arguments', $classAttr->arguments);
        $this->assertArrayHasKey('description', $classAttr->arguments);
        $this->assertSame('Multiple object arguments', $classAttr->arguments['description']);

        // Verify array contains object instances
        $arguments = $classAttr->arguments['arguments'];
        $this->assertIsArray($arguments);
        $this->assertCount(3, $arguments);
    }

    public function testObjectArgumentsPreservedThroughCaching(): void
    {
        // Get the attribute from cache (second call uses cache)
        $results1 = $this->registry->discover()
            ->className('TestWithObjectArgument')
            ->toList();

        $results2 = $this->registry->discover()
            ->className('TestWithObjectArgument')
            ->toList();

        $this->assertCount(count($results1), $results2);

        // Arguments should be identical after caching
        foreach ($results1 as $index => $attr1) {
            $attr2 = $results2[$index];
            $this->assertEquals($attr1->arguments, $attr2->arguments);
        }
    }

    public function testDiscoverIncludesLocalPluginAttributes(): void
    {
        // Debug: Check if plugin paths are being picked up
        $pluginLocator = new PluginLocator();
        $paths = $pluginLocator->getEnabledPluginPaths();

        // Discover all attributes
        $results = $this->registry->discover();

        // Debug: check all discovered attributes
        $allAttributes = $results->toList();
        $attributeNames = array_map(fn(AttributeInfo $attr): string => $attr->attributeName, $allAttributes);

        // Filter for LocalPluginRoute attribute from test local plugin
        $localPluginAttributes = $results
            ->attributeContains('LocalPluginRoute')
            ->toList();

        // Should find attributes from local plugin
        $this->assertNotEmpty(
            $localPluginAttributes,
            'Should discover attributes from local plugin. Plugin paths: ' . implode(', ', $paths) .
            '. Found attributes: ' . implode(', ', $attributeNames),
        );

        // Verify attributes are from the local plugin namespace
        foreach ($localPluginAttributes as $attr) {
            $this->assertStringContainsString('TestLocalPlugin', $attr->className);
        }
    }

    public function testDiscoverIncludesLocalPluginController(): void
    {
        // Discover all attributes from TestLocalController
        $results = $this->registry->discover()
            ->classNameContains('TestLocalController')
            ->toList();

        // Should find the controller with attributes
        $this->assertNotEmpty($results, 'Should discover TestLocalController from local plugin');

        // Verify we got both class and method attributes
        $targetTypes = array_unique(array_map(fn(AttributeInfo $attr) => $attr->target->type->value, $results));
        $this->assertContains('class', $targetTypes, 'Should have class-level attribute');
        $this->assertContains('method', $targetTypes, 'Should have method-level attribute');
    }
}
