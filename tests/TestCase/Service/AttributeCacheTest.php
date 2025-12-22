<?php
declare(strict_types=1);

namespace AttributeRegistry\Test\TestCase\Service;

use AttributeRegistry\Service\AttributeCache;
use Cake\Cache\Cache;
use Cake\TestSuite\TestCase;

class AttributeCacheTest extends TestCase
{
    private AttributeCache $cache;

    protected function setUp(): void
    {
        parent::setUp();

        // Configure test cache
        Cache::setConfig('attribute_test', [
            'engine' => 'Array',
            'duration' => '+1 hour',
        ]);

        $this->cache = new AttributeCache('attribute_test');
    }

    protected function tearDown(): void
    {
        parent::tearDown();
        Cache::clear('attribute_test');
        Cache::drop('attribute_test');
    }

    public function testAttributeCacheCanBeCreated(): void
    {
        $cache = new AttributeCache();
        $this->assertInstanceOf(AttributeCache::class, $cache);

        $cacheWithConfig = new AttributeCache('attribute_test');
        $this->assertInstanceOf(AttributeCache::class, $cacheWithConfig);
    }

    public function testCacheCanBeDisabled(): void
    {
        $cache = new AttributeCache('attribute_test', false);
        $this->assertFalse($cache->isEnabled());
    }

    public function testCacheIsEnabledByDefault(): void
    {
        $cache = new AttributeCache('attribute_test');
        $this->assertTrue($cache->isEnabled());
    }

    public function testDisabledCacheReturnsNullOnGet(): void
    {
        $cache = new AttributeCache('attribute_test', false);
        $cache->set('test_key', ['data' => 'value']);

        $this->assertNull($cache->get('test_key'));
    }

    public function testDisabledCacheSetReturnsFalse(): void
    {
        $cache = new AttributeCache('attribute_test', false);
        $result = $cache->set('test_key', ['data' => 'value']);

        $this->assertFalse($result);
    }

    public function testCacheCanSetAndGetData(): void
    {
        $testData = ['key1' => 'value1', 'key2' => 'value2'];

        $result = $this->cache->set('test_key', $testData);
        $this->assertTrue($result);

        $retrievedData = $this->cache->get('test_key');
        $this->assertEquals($testData, $retrievedData);
    }

    public function testCacheReturnsNullForMissingKey(): void
    {
        $result = $this->cache->get('nonexistent_key');
        $this->assertNull($result);
    }

    public function testCacheCanDeleteData(): void
    {
        $testData = ['key1' => 'value1'];

        $this->cache->set('test_key', $testData);
        $this->assertNotNull($this->cache->get('test_key'));

        $result = $this->cache->delete('test_key');
        $this->assertTrue($result);

        $this->assertNull($this->cache->get('test_key'));
    }

    public function testCacheWithCustomDuration(): void
    {
        $testData = ['key' => 'value'];

        $result = $this->cache->set('test_key', $testData, 3600);
        $this->assertTrue($result);

        $retrievedData = $this->cache->get('test_key');
        $this->assertEquals($testData, $retrievedData);
    }

    public function testDisabledCacheDeleteReturnsFalse(): void
    {
        $cache = new AttributeCache('attribute_test', false);
        $result = $cache->delete('test_key');

        $this->assertFalse($result);
    }

    public function testDisabledCacheClearReturnsFalse(): void
    {
        $cache = new AttributeCache('attribute_test', false);
        $result = $cache->clear();

        $this->assertFalse($result);
    }
}
