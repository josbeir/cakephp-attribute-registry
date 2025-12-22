<?php
declare(strict_types=1);

namespace AttributeRegistry\Test\TestCase\Controller;

use AttributeRegistry\AttributeRegistry;
use AttributeRegistry\Service\AttributeCache;
use AttributeRegistry\Service\AttributeParser;
use AttributeRegistry\Service\AttributeScanner;
use AttributeRegistry\Service\PathResolver;
use Cake\Cache\Cache;
use Cake\Core\Configure;
use Cake\TestSuite\IntegrationTestTrait;
use Cake\TestSuite\TestCase;

/**
 * DebugKitController Test
 */
class DebugKitControllerTest extends TestCase
{
    use IntegrationTestTrait;

    protected function setUp(): void
    {
        parent::setUp();

        // Set up test cache
        if (Cache::getConfig('controller_test') === null) {
            Cache::setConfig('controller_test', [
                'engine' => 'Array',
                'duration' => '+1 hour',
            ]);
        }

        // Load test attributes
        $testDataPath = dirname(__DIR__, 2) . '/data';
        require_once $testDataPath . '/TestAttributes.php';

        // Create and inject a test registry
        $pathResolver = new PathResolver($testDataPath);
        $cache = new AttributeCache('controller_test', false);
        $parser = new AttributeParser();

        $scanner = new AttributeScanner(
            $parser,
            $pathResolver,
            [
                'paths' => ['*.php'],
                'exclude_paths' => [],
                'max_file_size' => 1024 * 1024,
            ],
        );

        $registry = new AttributeRegistry($scanner, $cache);
        AttributeRegistry::setInstance($registry);
    }

    protected function tearDown(): void
    {
        parent::tearDown();
        AttributeRegistry::setInstance(null);
        Configure::delete('AttributeRegistry');
        Cache::clear('controller_test');
    }

    public function testDiscoverReturnsJson(): void
    {
        $this->disableErrorHandlerMiddleware();
        $this->configRequest([
            'headers' => ['Accept' => 'application/json'],
        ]);
        $this->post('/attribute-registry/debug-kit/discover');

        $this->assertResponseOk();
        $this->assertContentType('application/json');

        // Rewind stream before reading
        $response = $this->_response;
        $this->assertNotNull($response);
        $response->getBody()->rewind();
        $body = $response->getBody()->getContents();
        $this->assertNotEmpty($body, 'Response body should not be empty');
        $data = json_decode($body, true);

        $this->assertNotNull($data, 'Failed to decode JSON: ' . $body);
        $this->assertTrue($data['success']);
        $this->assertArrayHasKey('count', $data);
        $this->assertArrayHasKey('attributes', $data);
        $this->assertGreaterThan(0, $data['count']);
    }

    public function testDiscoverClearsCache(): void
    {
        // First, populate the registry
        $registry = AttributeRegistry::getInstance();
        $initialAttributes = $registry->discover();
        $initialCount = count($initialAttributes);

        $this->configRequest([
            'headers' => ['Accept' => 'application/json'],
        ]);
        $this->post('/attribute-registry/debug-kit/discover');

        $this->assertResponseOk();

        $response = $this->_response;
        $this->assertNotNull($response);
        $response->getBody()->rewind();
        $body = $response->getBody()->getContents();
        $data = json_decode($body, true);

        // Should have re-discovered attributes
        $this->assertSame($initialCount, $data['count']);
    }

    public function testDiscoverRequiresPostMethod(): void
    {
        $this->configRequest([
            'headers' => ['Accept' => 'application/json'],
        ]);
        $this->get('/attribute-registry/debug-kit/discover');

        $this->assertResponseCode(405);
    }

    public function testDiscoverWithEmptyPaths(): void
    {
        // Create a registry with non-existent paths
        $pathResolver = new PathResolver('/non/existent/path');
        $cache = new AttributeCache('controller_test', false);
        $parser = new AttributeParser();

        $scanner = new AttributeScanner(
            $parser,
            $pathResolver,
            [
                'paths' => ['*.php'],
                'exclude_paths' => [],
                'max_file_size' => 1024 * 1024,
            ],
        );

        $emptyRegistry = new AttributeRegistry($scanner, $cache);
        AttributeRegistry::setInstance($emptyRegistry);

        $this->configRequest([
            'headers' => ['Accept' => 'application/json'],
        ]);
        $this->post('/attribute-registry/debug-kit/discover');

        $this->assertResponseOk();

        $response = $this->_response;
        $this->assertNotNull($response);
        $response->getBody()->rewind();
        $body = $response->getBody()->getContents();
        $data = json_decode($body, true);

        $this->assertTrue($data['success']);
        $this->assertSame(0, $data['count']);
        $this->assertEmpty($data['attributes']);
    }
}
