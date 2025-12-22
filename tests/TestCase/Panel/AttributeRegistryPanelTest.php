<?php
declare(strict_types=1);

namespace AttributeRegistry\Test\TestCase\Panel;

use AttributeRegistry\AttributeRegistry;
use AttributeRegistry\Panel\AttributeRegistryPanel;
use AttributeRegistry\Test\TestCase\AttributeRegistryTestTrait;
use Cake\Cache\Cache;
use Cake\Core\Configure;
use Cake\TestSuite\TestCase;

/**
 * AttributeRegistryPanel Test
 */
class AttributeRegistryPanelTest extends TestCase
{
    use AttributeRegistryTestTrait;

    private AttributeRegistryPanel $panel;

    protected function setUp(): void
    {
        parent::setUp();

        // Set up test cache
        if (Cache::getConfig('panel_test') === null) {
            Cache::setConfig('panel_test', [
                'engine' => 'Array',
                'duration' => '+1 hour',
            ]);
        }

        $this->loadTestAttributes();

        // Create and inject a test registry
        $registry = $this->createRegistry('panel_test');
        AttributeRegistry::setInstance($registry);

        // Configure for config assertions
        Configure::write('AttributeRegistry', [
            'scanner' => ['paths' => ['*.php']],
            'cache' => ['enabled' => false],
        ]);

        $this->panel = new AttributeRegistryPanel();
    }

    protected function tearDown(): void
    {
        parent::tearDown();
        AttributeRegistry::setInstance(null);
        Configure::delete('AttributeRegistry');
        Cache::clear('panel_test');
    }

    public function testDataReturnsAttributes(): void
    {
        $data = $this->panel->data();

        $this->assertArrayHasKey('attributes', $data);
        $this->assertArrayHasKey('count', $data);
        $this->assertArrayHasKey('groupedByAttribute', $data);
        $this->assertArrayHasKey('groupedByTarget', $data);
        $this->assertArrayHasKey('config', $data);
    }

    public function testDataContainsDiscoveredAttributes(): void
    {
        $data = $this->panel->data();

        $this->assertGreaterThan(0, $data['count']);
        $this->assertNotEmpty($data['attributes']);
    }

    public function testDataGroupedByAttributeIsCorrect(): void
    {
        $data = $this->panel->data();

        $this->assertIsArray($data['groupedByAttribute']);
        // Should have at least TestAttribute
        $this->assertNotEmpty($data['groupedByAttribute']);

        // Each group should be an array of AttributeInfo
        foreach ($data['groupedByAttribute'] as $attributeName => $attributes) {
            $this->assertIsString($attributeName);
            $this->assertIsArray($attributes);
        }
    }

    public function testDataGroupedByTargetIsCorrect(): void
    {
        $data = $this->panel->data();

        $this->assertIsArray($data['groupedByTarget']);
        $this->assertNotEmpty($data['groupedByTarget']);

        // Each group should be keyed by file path
        foreach ($data['groupedByTarget'] as $targetFile => $attributes) {
            $this->assertIsString($targetFile);
            $this->assertIsArray($attributes);
        }
    }

    public function testDataContainsConfig(): void
    {
        $data = $this->panel->data();

        $this->assertIsArray($data['config']);
        $this->assertArrayHasKey('scanner', $data['config']);
    }

    public function testSummaryReturnsCount(): void
    {
        $summary = $this->panel->summary();

        $this->assertGreaterThan(0, (int)$summary);
    }

    public function testSummaryWithNoAttributes(): void
    {
        // Create a registry with non-existent paths
        $scanner = $this->createScanner(
            pathResolver: $this->createPathResolver('/non/existent/path'),
        );
        $cache = $this->createCache('panel_test');

        $emptyRegistry = new AttributeRegistry($scanner, $cache);
        AttributeRegistry::setInstance($emptyRegistry);

        $panel = new AttributeRegistryPanel();
        $summary = $panel->summary();

        $this->assertSame('0', $summary);
    }

    public function testPanelTitle(): void
    {
        $this->assertSame('Attributes', $this->panel->title());
    }
}
